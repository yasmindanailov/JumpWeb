#!/usr/bin/env python3
"""Arnés de mutación de `#521` — los DESTINOS del menú (y del pie): el inventario del canvas.

Mismo molde endurecido que `mutar-bandeja.py` (`#506`), con sus cuatro reglas pagadas:
  · en PYTHON y no en bash: con heredocs el escapado de `$` se perdía y cinco mutaciones
    «sobrevivieron» sin haberse aplicado nunca — aquí cada una verifica que el fichero CAMBIÓ;
  · EXIGE VERDE antes de mutar (`#337`): un filtro que no casa con nada sale ≠ 0 y el arnés
    cantaría «muere» sin haber ejecutado un caso;
  · EXIGE ÁRBOL LIMPIO en los ficheros que muta (`#181`): restaurar con `git checkout` se lleva el
    trabajo sin commitear;
  · restaura SIEMPRE, también si el proceso revienta (`#448`).

    python3 scripts/mutar-destinos.py
"""
import subprocess
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
FILTRO = 'ArmazonContractTest|MenuGroupsTest|LandingServiceResourceTest'
FICHEROS = [
    'app/Domain/Content/Services/SiteDestinations.php',
    'app/Http/Controllers/HomeController.php',
    'resources/views/components/site/nav.blade.php',
    'resources/views/components/site/menu.blade.php',
    'app/Filament/Resources/LandingServices/Concerns/InteractsWithLandingServiceForm.php',
]

# (nombre, fichero, texto que se busca, texto por el que se cambia)
MUTACIONES = [
    ("se cuela un destino que no está en el inventario",
     'app/Domain/Content/Services/SiteDestinations.php',
     "        'precios' => 'pricing',\n",
     "        'precios' => 'pricing',\n        'entradas' => 'pricing',\n"),

    ("el inventario cambia de orden",
     'app/Domain/Content/Services/SiteDestinations.php',
     "        'precios' => 'pricing',\n        'cumpleanos' => 'events',\n",
     "        'cumpleanos' => 'events',\n        'precios' => 'pricing',\n"),

    ("una página en mantenimiento se sigue anunciando",
     'app/Domain/Content/Services/SiteDestinations.php',
     "if (MaintenanceSettings::pageInMaintenance($route)) {",
     "if (false) {"),

    ("una sección se anuncia con un rótulo propio y no con el suyo",
     'app/Domain/Content/Services/SiteDestinations.php',
     "'zones' => 'landing.zones.eyebrow',",
     "'zones' => 'landing.nav.zones',"),

    ("«Dudas» se ofrece aunque la portada no la pinte (el ancla muerta)",
     'app/Http/Controllers/HomeController.php',
     "homeSections(withFaq: $faqs->isNotEmpty())",
     "homeSections(withFaq: true)"),

    ("las secciones de la portada vuelven a colarse en las interiores (el defecto REAL)",
     'resources/views/components/site/nav.blade.php',
     "array_merge($sections, \\App",
     "array_merge($sections ?: \\App\\Domain\\Content\\Services\\SiteDestinations::homeSections(), \\App"),

    ("el cajón deja de leer los mismos destinos que el menú",
     'resources/views/components/site/nav.blade.php',
     "@foreach ($menuGroups as $clave => $delGrupo)",
     "@foreach (['page' => array_slice($menuGroups['page'], 1)] as $clave => $delGrupo)"),

    ("la página en curso deja de ir marcada",
     'resources/views/components/site/menu.blade.php',
     "@if (! empty($item['current'])) aria-current=\"page\" @endif",
     ""),

    ("guardar un servicio vuelve a reescribir show_in_nav (la regresión evitada)",
     'app/Filament/Resources/LandingServices/Concerns/InteractsWithLandingServiceForm.php',
     "        $data['is_active'] = (bool) ($data['is_active'] ?? true);\n",
     "        $data['is_active'] = (bool) ($data['is_active'] ?? true);\n        $data['show_in_nav'] = true;\n"),
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
