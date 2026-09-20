#!/usr/bin/env python3
"""Arnés de mutación de `#657` — `/atracciones` se muda a la instancia (F5 · T2b).

Mismo molde endurecido que `mutar-normas.py` (`#655`) y `mutar-bar.py`:
  · en PYTHON, y cada mutación verifica que el fichero CAMBIÓ antes de correr nada;
  · EXIGE VERDE antes de mutar (`#337`) y ÁRBOL LIMPIO en los ficheros que muta (`#181`);
  · restaura SIEMPRE, también si el proceso revienta (`#448`).

⚠️⚠️ **La página de PlayJump ya no está en el producto**, así que las guardas se parten por lo que afirman
(`#649`): `AttractionsPageTest` afirma sobre los DATOS de la vista (`zones`, `total`, `active`) y mata los
mutantes del controlador y del modelo; los de VISTA apuntan al ANFITRIÓN MÍNIMO
(`anfitrion/atracciones.blade.php`) y los mata `AnfitrionAtraccionesTest`; el tinte de la cifra lo vigila
`ZoneInkIsLegibleTest`, y la identidad por `slug`, `ZoneIdentityIsUniqueTest`. Lo que era DISEÑO se fue con
la vista y su juez es la huella de maquetación (38 pantallas), no esto.

    python3 scripts/mutar-atracciones.py
"""
import subprocess
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
FILTRO = 'AttractionsPageTest|AnfitrionAtraccionesTest|ZoneInkIsLegibleTest|ZoneIdentityIsUniqueTest|InstanceViewContractTest'
CONTROLADOR = 'app/Http/Controllers/AttractionsController.php'
MODELO = 'app/Domain/Content/Models/Attraction.php'
VISTA = 'resources/views/anfitrion/atracciones.blade.php'
FICHEROS = [CONTROLADOR, MODELO, VISTA]

# (nombre, fichero, texto que se busca, texto por el que se cambia[, cuántas veces se espera])
MUTACIONES = [
    # ── Qué viaja a la vista ─────────────────────────────────────────────────────────────────────
    ("una zona SIN atracciones viaja igual (y la landing abre una rejilla vacía)",
     CONTROLADOR,
     "->filter(fn (Zone $zone): bool => $zone->attractions->isNotEmpty())",
     "->filter(fn (Zone $zone): bool => true)"),

    ("una zona apagada para la landing viaja igual",
     CONTROLADOR,
     "->where('show_in_landing', true)->orderBy('position')->get()",
     "->orderBy('position')->get()"),

    ("las zonas se ordenan al revés que el panel",
     CONTROLADOR,
     "->where('show_in_landing', true)->orderBy('position')->get()",
     "->where('show_in_landing', true)->orderByDesc('position')->get()"),

    ("una atracción APAGADA se publica igual",
     CONTROLADOR,
     "fn ($q) => $q->where('is_active', true)->orderBy('position')",
     "fn ($q) => $q->orderBy('position')"),

    # ── El recuento, que la portada repite ───────────────────────────────────────────────────────
    ("el recuento se hace con `Attraction::count()` y no con lo que la página enseña",
     CONTROLADOR,
     "'total' => $zones->sum(fn (Zone $zone): int => $zone->attractions->count()),",
     "'total' => \\App\\Domain\\Content\\Models\\Attraction::count(),"),

    # ── La zona de llegada ───────────────────────────────────────────────────────────────────────
    ("un `?zona=` desconocido se pasa crudo (y ninguna pestaña arranca elegida)",
     CONTROLADOR,
     "'active' => $zones->firstWhere('slug', $requested)?->slug ?? $zones->first()?->slug,",
     "'active' => $requested,"),

    # ── El contrato de vista: lo que se le promete a CADA instancia ──────────────────────────────
    ("el controlador deja de pasar una variable que el contrato promete (todas las landings a la vez)",
     CONTROLADOR,
     "'active' => $zones->firstWhere('slug', $requested)?->slug ?? $zones->first()?->slug,",
     ""),

    # ── La foto, que es regla del producto desde `#657` ──────────────────────────────────────────
    ("la foto de una atracción se resuelve como si fuera una SUBIDA (`uploads/`)",
     MODELO,
     "return $ruta === '' ? null : asset($ruta);",
     "return $ruta === '' ? null : asset('uploads/'.$ruta);"),

    ("una ruta de foto vacía devuelve la RAÍZ del sitio en vez de `null` (y se pinta un roto)",
     MODELO,
     "return $ruta === '' ? null : asset($ruta);",
     "return asset($ruta);"),

    # ── El anfitrión mínimo: la identidad de una zona ────────────────────────────────────────────
    ("el panel de zona se identifica por ACENTO y no por `slug` (tres zonas abren a la vez)",
     VISTA,
     'id="atracciones-{{ $zone->slug }}"',
     'id="atracciones-{{ $zone->accent }}"'),

    ("la pestaña deja de controlar su panel (`aria-controls` fuera)",
     VISTA,
     'aria-controls="atracciones-{{ $zone->slug }}"',
     ''),

    # ── El anfitrión mínimo: el dato que se pinta ────────────────────────────────────────────────
    ("la paleta compuesta deja de viajar (la cifra se pinta con el respaldo y nadie se entera)",
     VISTA,
     'style="{{ \\App\\Domain\\Content\\Services\\ThemeSettings::zoneStyle($zone->color, $zone->color_secondary, $zone->accent) }}"',
     'style=""',
     2),

    ("sin foto se reserva hueco igual (un cuadrado roto esperando)",
     VISTA,
     "@if ($foto = $ride->imageUrl())",
     "@if (true)"),

    ("el «qué es» se pinta aunque la atracción no lo tenga",
     VISTA,
     "@if ($ride->tr('description'))",
     "@if (true)"),

    ("el DISTINTIVO deja de pintarse (y `attractions.badge` se queda sin pantalla que lo publique)",
     VISTA,
     "@if ($ride->tr('badge'))\n                                                <span class=\"ride-tile__badge\">",
     "@if (false)\n                                                <span class=\"ride-tile__badge\">"),

    ("con UNA sola zona se pinta igual la pestaña (un control de una opción)",
     VISTA,
     '@if ($zones->count() > 1)\n                        <div class="tabset rides-page__tabs"',
     '@if (true)\n                        <div class="tabset rides-page__tabs"'),

    ("el pie se pinta sin nota del panel (un renglón vacío que promete una regla)",
     VISTA,
     "@if ($site['zones_access'] ?? null)",
     "@if (true)"),

    ("las ocultas se eligen por POSICIÓN y no por la zona que llega (`?zona=kids` parpadea en Jump)",
     VISTA,
     "@if ($zone->slug !== $active) x-cloak @endif",
     "@if (! $loop->first) x-cloak @endif",
     2),
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
