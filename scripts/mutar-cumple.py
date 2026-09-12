#!/usr/bin/env python3
"""Arnés de mutación de `#528` — `/cumpleanos` rehecha desde su artboard (T3b): el total que cobra
la cesta, la especial entera, el reparto entre la tabla y «Igual en los dos», el reloj, el menú
fuera del carril, lo que se añade después de reservar, la tarjeta mixta, el contador sin
JavaScript, el botón de reservar, la página vacía y lo retirado.

Mismo molde endurecido que `mutar-cabecera.py` (`#525`):
  · en PYTHON, y cada mutación verifica que el fichero CAMBIÓ antes de correr nada;
  · EXIGE VERDE antes de mutar (`#337`) y ÁRBOL LIMPIO en los ficheros que muta (`#181`);
  · restaura SIEMPRE, también si el proceso revienta (`#448`).

    python3 scripts/mutar-cumple.py
"""
import subprocess
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
FILTRO = 'BirthdayPageTest'
SERVICIO = 'app/Domain/Booking/Services/BirthdayComparison.php'
VISTA = 'resources/views/pages/events.blade.php'
FICHEROS = [SERVICIO, VISTA]

# (nombre, fichero, texto que se busca, texto por el que se cambia)
MUTACIONES = [
    # ── El dinero ──
    ("el precio por niño ignora los TRAMOS (el de escaparate en vez del de la cesta)",
     SERVICIO,
     "$each = $ok ? $pack->priceCentsForRate($normals[$i], $n) : null;",
     "$each = $ok ? $pack->displayPriceCents() : null;"),

    ("el total deja de multiplicar por el número de niños",
     SERVICIO,
     "$live['total'][$n][] = $each === null ? null : $this->euros($each * $n);",
     "$live['total'][$n][] = $each === null ? null : $this->euros($each);"),

    ("el contador ignora el máximo de los packs",
     SERVICIO,
     "$to = (int) $packs->map(fn (TicketType $p): int => (int) ($p->max_qty ?? 0))->max();",
     "$to = 30;"),

    ("la especial se publica como RECARGO",
     SERVICIO,
     "$eachSpecial = $ok && $special ? $pack->priceCentsForRate($special, $n) : null;",
     "$eachSpecial = $ok && $special ? $pack->priceCentsForRate($special, $n) - $pack->priceCentsForRate($normals[$i], $n) : null;"),

    ("las filas de la especial se quedan aunque repitan las de arriba",
     SERVICIO,
     "            && $live['each_special'] !== $live['each']\n",
     ""),

    ("la nota de los días sale aunque no haya filas de la especial",
     VISTA,
     "                            @if ($compare['special'])",
     "                            @if (true)"),

    # ── La regla de la comparativa ──
    ("un hecho igual en todos los packs baja a la tabla en vez de a «Igual»",
     SERVICIO,
     "        if ($this->same($values)) {\n            $shared[] =",
     "        if (false) {\n            $shared[] ="),

    ("ninguna ventaja se reconoce como común",
     SERVICIO,
     "collect($lists)->every(fn (array $list): bool => in_array($f, $list, true))",
     "false"),

    ("el reloj habla por una duración que no comparten",
     SERVICIO,
     "$duration = $this->same($durations) ? $durations[0] : null;",
     "$duration = $durations[0];"),

    # ── Los complementos ──
    ("el carril vuelve a llevar el menú",
     VISTA,
     ':without-choices="true"',
     ':without-choices="false"'),

    ("lo que se añade después desaparece de la página",
     SERVICIO,
     "foreach ($pack->addonsSoldAfterBooking() as $addon) {",
     "foreach ([] as $addon) {"),

    ("lo que se añade después pierde su plazo",
     SERVICIO,
     "'cutoff' => $this->cutoff($addon->pivot->postformCutoffHours()),",
     "'cutoff' => null,"),

    ("la tarjeta de edades mezcladas sale sin familia compartida",
     SERVICIO,
     "return $size >= 2 ? $size : 0;",
     "return 2;"),

    # ── El contador, el botón y la página vacía ──
    ("el contador se ofrece sin JavaScript",
     VISTA,
     '<button type="button" class="party-count__btn" x-cloak\n                                    aria-label="{{ __(\'landing.birthday.count_less\') }}"',
     '<button type="button" class="party-count__btn"\n                                    aria-label="{{ __(\'landing.birthday.count_less\') }}"'),

    ("el contador sale aunque no haya tramo de niños",
     VISTA,
     "@if ($compare['counter']['to'] > $compare['counter']['from'])",
     "@if (true)"),

    ("el botón de reservar no abre nada",
     VISTA,
     "\n                                @click=\"$store.purchase.openWith({ type: 'packs' })\">",
     ">"),

    ("sin packs la página revienta en vez de decirlo",
     VISTA,
     "        @if (! $compare)",
     "        @if (false)"),

    # ── La foto de la zona (`#532`) ──
    ("la foto se pinta aunque el panel no tenga ninguna (hueco gris esperando)",
     VISTA,
     "                @if ($zone?->image)",
     "                @if (true)"),

    ("el alt de la foto deja de decir de qué zona es",
     VISTA,
     'alt="{{ $zone->tr(\'name\') }}"',
     'alt=""'),

    # ── Lo retirado ──
    ("vuelve una clase de la página vieja",
     VISTA,
     '<main id="main" class="page party-page wrap">',
     '<main id="main" class="page party-page wrap bd-page">'),
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
