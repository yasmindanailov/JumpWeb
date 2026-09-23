#!/usr/bin/env python3
"""Arnés de mutación de `#660` — `/servicios` se muda a la instancia (F5 · T2b), y con ella bajan al
producto las dos reglas de dinero que la vista escribía a mano y la de la foto.

Mismo molde endurecido que `mutar-cumple.py` y `mutar-precios.py`:
  · en PYTHON, y cada mutación verifica que el fichero CAMBIÓ antes de correr nada;
  · EXIGE VERDE antes de mutar (`#337`) y ÁRBOL LIMPIO en los ficheros que muta (`#181`);
  · restaura SIEMPRE, también si el proceso revienta (`#448`).

⚠️⚠️ **La página de PlayJump ya no está en el producto**, así que las guardas se parten por lo que
afirman (`#649`): `Landing\\ServicesPageTest` afirma sobre los DATOS —`services`, `groupRates`,
`groupFrom`, `birthdayCards`— y mata los mutantes del servicio y del controlador; los de VISTA apuntan
al ANFITRIÓN MÍNIMO (`anfitrion/servicios.blade.php`) y los mata `AnfitrionServiciosTest`.

    python3 scripts/mutar-servicios.py
"""
import subprocess
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
FILTRO = 'ServicesPageTest|AnfitrionServiciosTest|SidebarSeamTest'
SERVICIO = 'app/Domain/Booking/Services/GroupRateTables.php'
CONTROLADOR = 'app/Http/Controllers/ServicesController.php'
MODELO = 'app/Domain/Content/Models/LandingService.php'
VISTA = 'resources/views/anfitrion/servicios.blade.php'
FICHEROS = [SERVICIO, CONTROLADOR, MODELO, VISTA]

# (nombre, fichero, texto que se busca, texto por el que se cambia[, cuántas veces se espera])
MUTACIONES = [
    # ── El «desde»: QUÉ precio se anuncia ──────────────────────────────────────────────────────────
    ("el «desde» anuncia el precio MÁS CARO de sus tablas",
     SERVICIO,
     "$cents = collect($tables)->pluck('lowest_cents')->filter()->min();",
     "$cents = collect($tables)->pluck('lowest_cents')->filter()->max();"),

    # ⚠️ AQUÍ VIVÍA «el «desde» deja de saltarse los `null`», y se RETIRA por EQUIVALENTE (`#660`):
    # medido en tinker, `Collection::min()` ya ignora los `null` —`collect([null, 1800])->min()` da 1800—,
    # así que quitar el `filter()` no cambia la conducta y ningún test puede matarlo. Un mutante que no se
    # puede matar no mide una guarda floja: mide que el mutante estaba mal pensado.

    # ── Las FILAS: solo lo que la cesta alcanza (`#677`) ────────────────────────────────────────────
    # ⚠️ La regla la comparte `/api/v1/prices` (`GroupRateTables::quantities()`), y su propio arnés
    # (`mutar-menu-de-hechos.sh`) la muta también: aquí se prueba que la VISTA la consume.
    ("un tramo por encima del MÁXIMO del pack se anuncia en la tabla (y el «desde» sale de él)",
     SERVICIO,
     "->filter(fn (int $q): bool => $q >= $minimo && ($maximo === null || $q <= $maximo))",
     "->filter(fn (int $q): bool => $q >= $minimo)"),

    # ── El «desde»: CÓMO se escribe (el defecto de `#660`) ─────────────────────────────────────────
    ("el «desde» vuelve al registro de TRANSACCIÓN (dos decimales fijos y coma en inglés)",
     SERVICIO,
     "return $cents === null ? null : $this->euros((int) $cents);\n    }\n\n    private function written",
     "return $cents === null ? null : \\App\\Domain\\Platform\\Services\\Money::format((int) $cents);\n    }\n\n    private function written"),

    # ── El dinero de la tabla TECLEADA, en la vista ────────────────────────────────────────────────
    ("la tabla tecleada vuelve a escribir el dinero a mano (y en inglés pone coma)",
     VISTA,
     "\\App\\Domain\\Platform\\Services\\Money::showcase($c).' €'",
     "$c % 100 === 0 ? intdiv($c, 100).' €' : \\App\\Domain\\Platform\\Services\\Money::format($c)"),

    # ── La foto ────────────────────────────────────────────────────────────────────────────────────
    ("la ruta de la foto se vuelve a derivar en la vista (y `uploads/` está al lado)",
     VISTA,
     "@if ($foto = $service->imageUrl())",
     "@if ($foto = $service->image ? asset('uploads/'.$service->image) : null)"),

    ("una ruta de foto vacía devuelve la RAÍZ del sitio en vez de `null`",
     MODELO,
     "        return $ruta === '' ? null : asset($ruta);",
     "        return asset($ruta);"),

    # ── Qué servicios viajan ───────────────────────────────────────────────────────────────────────
    ("un servicio apagado en el panel se publica igual",
     CONTROLADOR,
     "$services = LandingService::active()->ordered()",
     "$services = LandingService::ordered()"),

    ("el controlador deja de pasar el «desde» que el contrato promete",
     CONTROLADOR,
     "            'groupFrom' => array_map(fn (array $t): ?string => $groupRates->lowestWritten($t), $tablas),",
     ""),

    # ── El anfitrión mínimo: lo que el producto sirve SIN paquete ──────────────────────────────────
    ("el ancla estable de cada servicio desaparece (el menú enlaza a la nada)",
     VISTA,
     'id="{{ $service->slug }}"',
     'id="servicio"'),

    ("el «Reservar» deja de declarar QUÉ producto abre",
     VISTA,
     "openWith({ type: 'product', id: {{ $table['id'] }} })",
     "open()"),

    ("con una sola zona se pintan pestañas igual",
     VISTA,
     "@if (count($service->price_table['zones']) > 1)",
     "@if (true)"),

    ("sin servicios la página pinta filas vacías en vez de degradar",
     VISTA,
     "        @if ($services->isNotEmpty())\n            <div class=\"svc-ed2\">",
     "        @if (true)\n            <div class=\"svc-ed2\">"),
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

    print('── CONTROL: las guardas tienen que estar VERDES antes de mutar ──')
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
            esperadas = mutacion[4] if len(mutacion) > 4 else 1
            ruta = RAIZ / rel
            antes = ruta.read_text(encoding='utf-8')
            n = antes.count(busca)
            if n != esperadas:
                # ⚠️ Una mutación que no se aplica NO es una guarda que aguanta.
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
