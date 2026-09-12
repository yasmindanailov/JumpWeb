#!/usr/bin/env python3
"""Arnés de mutación de las BANDAS DE ENLACE (carril de diseño Fase 3, artboard `Bandas PJP`):
la banda gorda que contesta la pregunta que la página deja abierta y las dos franjas finas.

Mismo molde endurecido que `mutar-bar.py` (`#536`) y `mutar-contacto.py` (`#535`):
  · en PYTHON, y cada mutación verifica que el fichero CAMBIÓ antes de correr nada;
  · EXIGE VERDE antes de mutar (`#337`) y ÁRBOL LIMPIO en los ficheros que muta (`#181`);
  · restaura SIEMPRE, también si el proceso revienta (`#448`).

⚠️ **Varias mutaciones prueban una AUSENCIA** —que `/bar` y `/contacto` NO lleven gorda, que un
destino apagado retire la pieza, que sin nada que ofrecer no se pinte nada— y ésas se mutan al
revés: se AÑADE lo que no debe estar, o se RETIRA la condición que lo impide.

⚠️⚠️ **La última mutación no es de esta pieza y está aquí a propósito**: retira la re-declaración
por superficie del rol SECUNDARIO, que es el defecto real que `#540` dejó puesto y que ninguna
guarda veía. La banda fue el séptimo consumidor en caer; el primero era el CTA del hero de la
portada.

    python3 scripts/mutar-bandas.py
"""
import subprocess
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent

SERVICIO = 'app/Domain/Content/Services/LinkBands.php'
COMPONENTE = 'resources/views/components/site/link-bands.blade.php'
HOJA = 'public/css/landing.css'
LANG_FR = 'lang/fr/site.php'
BAR = 'resources/views/pages/bar.blade.php'
FICHEROS = [SERVICIO, COMPONENTE, HOJA, LANG_FR, BAR]

# Las guardas que tienen que morder. Se pasan como RUTAS y no como `--filter`, porque el filtro por
# nombre de clase no distingue dos casos homónimos y aquí entran cinco ficheros.
PRUEBAS = [
    'tests/Feature/Site/LinkBandsTest.php',
    'tests/Feature/Architecture/SurfaceScopeTest.php',
    'tests/Feature/Landing/BarPageTest.php',
    'tests/Feature/Landing/AttractionsPageTest.php',
    'tests/Feature/Site/PricingPageTest.php',
]

# (nombre, fichero, texto que se busca, texto por el que se cambia[, cuántas veces se espera])
MUTACIONES = [
    # ── Los topes del canvas ──────────────────────────────────────────────────────────────────
    ("`/bar` gana una gorda, contra el «cero relleno de acción» de `#536`",
     SERVICIO,
     "        'cumpleanos' => 'bar',\n",
     "        'cumpleanos' => 'bar',\n        'bar' => 'atracciones',\n"),

    ("una gorda lleva a la página en la que ya estás",
     SERVICIO,
     "        'normas' => 'precios',",
     "        'normas' => 'normas',"),

    ("una página declara TRES finas: el menú otra vez",
     SERVICIO,
     "        'atracciones' => ['precios', 'bar'],",
     "        'atracciones' => ['precios', 'bar', 'normas'],"),

    ("`/contacto` gana una gorda al lado de su formulario",
     SERVICIO,
     "        'servicios' => 'contacto',",
     "        'servicios' => 'contacto',\n        'contacto' => 'precios',"),

    # ── El reparto sale del INVENTARIO ────────────────────────────────────────────────────────
    ("un destino apagado se SUSTITUYE por otro en vez de retirar la pieza",
     SERVICIO,
     "        $pagina = self::offered()[$destino] ?? null;\n\n        if ($pagina === null) {\n            return null;\n        }",
     "        $pagina = self::offered()[$destino] ?? ['s' => '/'.$destino, 'url' => '/'.$destino];"),

    ("la pieza compone sus URL a mano y deja de mirar el inventario",
     SERVICIO,
     "        foreach (SiteDestinations::pages() as $pagina) {\n            $porRuta[$pagina['route']] = $pagina;\n        }",
     "        foreach (SiteDestinations::PAGES as $ruta => $etq) {\n            $porRuta[$ruta] = ['route' => $ruta, 't' => $ruta, 'url' => '/'.$ruta, 's' => '/'.$ruta, 'current' => false];\n        }"),

    ("un destino del reparto no existe en el inventario",
     SERVICIO,
     "        'precios' => 'cumpleanos',",
     "        'precios' => 'galeria',"),

    # ── La forma ──────────────────────────────────────────────────────────────────────────────
    ("se pinta el contenedor aunque no haya nada que ofrecer",
     COMPONENTE,
     "@if ($gorda || $finas)",
     "@if (true)"),

    ("la superficie sube de la tarjeta a su contenedor (`#484`)",
     COMPONENTE,
     '<div {{ $attributes->class(\'bands\') }}>',
     '<div {{ $attributes->class(\'bands\') }} data-surface="ink">'),

    ("el rótulo deja de ser la RUTA del destino y pasa a su nombre",
     COMPONENTE,
     '<p class="band-wide__route">{{ $gorda[\'s\'] }}</p>',
     '<p class="band-wide__route">{{ $gorda[\'q\'] }}</p>'),

    # ── La familia es UNA ─────────────────────────────────────────────────────────────────────
    ("vuelve el cierre a medida de `/bar`, la quinta piel",
     BAR,
     '<p class="bar-party__line">{{ __(\'site.bar_party_line\') }}</p>',
     '<p class="bar-party__line">{{ __(\'site.bar_party_line\') }}</p><a class="bar-party__cta" href="#">x</a>'),

    # ── El diccionario ────────────────────────────────────────────────────────────────────────
    ("falta un rótulo en francés: la banda se rotularía con la clave en crudo",
     LANG_FR,
     "        'lead' => 'Continue par ici',\n",
     ""),

    # ── El rol secundario, que es el defecto de `#540` ────────────────────────────────────────
    ("el rol SECUNDARIO deja de seguir a la superficie de tinta (el defecto de `#540`)",
     HOJA,
     "  --secondary: var(--interactive);\n",
     "",
     2),
]


def git(*args):
    return subprocess.run(['git', *args], cwd=RAIZ, capture_output=True, text=True)


def restaura():
    git('checkout', '-q', '--', *FICHEROS)


def verde():
    r = subprocess.run(
        ['docker', 'compose', 'exec', '-u', 'sail', '-T', 'laravel.test',
         'php', 'artisan', 'test', *PRUEBAS],
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
        print(salida[-2000:])
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
