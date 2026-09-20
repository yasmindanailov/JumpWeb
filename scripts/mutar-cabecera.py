#!/usr/bin/env python3
"""Arnés de mutación de `#525` — la CABECERA DE PÁGINA del armazón (T3a·3): la ruta escrita, la
entradilla opcional, las pantallas de servicio sin su URL, la decoración dentro del conjunto
rótulo + titular, la declaración compartida con la de sección y el aire derivado del racimo.

Mismo molde endurecido que `mutar-pie.py` (`#522`) y `mutar-destinos.py` (`#521`):
  · en PYTHON, y cada mutación verifica que el fichero CAMBIÓ antes de correr nada;
  · EXIGE VERDE antes de mutar (`#337`) y ÁRBOL LIMPIO en los ficheros que muta (`#181`);
  · restaura SIEMPRE, también si el proceso revienta (`#448`).

    python3 scripts/mutar-cabecera.py
"""
import subprocess
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
FILTRO = 'PageHeadTest'
COMPONENTE = 'resources/views/components/site/page-head.blade.php'
FICHEROS = [
    COMPONENTE,
    'app/Domain/Content/Services/SiteDestinations.php',
    'public/css/landing.css',
    'public/css/site.css',
    'resources/views/pages/pricing.blade.php',
    'resources/views/pages/rules.blade.php',
    # `/contacto` es el ANFITRIÓN MÍNIMO desde `#654`: la landing de PlayJump vive en su instancia.
    'resources/views/anfitrion/contacto.blade.php',
    'resources/views/auth/reset-password.blade.php',
    'resources/views/errors/page-maintenance.blade.php',
]

# (nombre, fichero, texto que se busca, texto por el que se cambia)
MUTACIONES = [
    # ── El rótulo: la ruta ESCRITA ──
    ("el rótulo escribe la URL entera en vez de la ruta",
     COMPONENTE,
     "$eyebrow ??= \\App\\Domain\\Content\\Services\\SiteDestinations::writtenPath(url()->current());",
     "$eyebrow ??= url()->current();"),

    ("la ruta escrita pierde su barra inicial (menú y cabecera a la vez)",
     'app/Domain/Content/Services/SiteDestinations.php',
     "return (string) (parse_url($url, PHP_URL_PATH) ?: '/');",
     "return ltrim((string) parse_url($url, PHP_URL_PATH), '/');"),

    # ── La entradilla ──
    ("sin entradilla se pinta igual un párrafo vacío",
     COMPONENTE,
     "    @if (filled($lede))",
     "    @if (true)"),

    ("/normas pierde la entradilla que el owner aprobó",
     'resources/views/pages/rules.blade.php',
     " :lede=\"__('site.rules_intro')\"",
     ""),

    ("/contacto devuelve su frase al cuerpo de la página",
     'resources/views/anfitrion/contacto.blade.php',
     "            :lede=\"$heroStatus ? __('site.contact_intro') : __('site.contact_intro_plain')\" />",
     "            />"),

    # ── Las pantallas de servicio ──
    ("restablecer la contraseña deriva el rótulo de su URL (imprime el TOKEN)",
     'resources/views/auth/reset-password.blade.php',
     "<x-site.page-head :eyebrow=\"__('account.reset.eyebrow')\" ",
     "<x-site.page-head "),

    ("la pantalla de mantenimiento anuncia la ruta de la página caída",
     'resources/views/errors/page-maintenance.blade.php',
     "<x-site.page-head :eyebrow=\"__('site.page_maintenance.eyebrow')\" ",
     "<x-site.page-head "),

    # ── La decoración: dentro del conjunto, lejos de la entradilla (el defecto REAL) ──
    ("la decoración vuelve a colgar de la cabecera entera (cae sobre la entradilla)",
     COMPONENTE,
     "    <div @class(['page__lockup', 'page__lockup--deco' => isset($deco)])>\n        {{ $deco ?? '' }}\n",
     "    {{ $deco ?? '' }}\n    <div @class(['page__lockup', 'page__lockup--deco' => isset($deco)])>\n"),

    ("el conjunto decorado deja de marcarse (y de recortar)",
     COMPONENTE,
     "'page__lockup--deco' => isset($deco)",
     "'page__lockup--deco' => false"),

    ("el conjunto decorado deja de recortar",
     'public/css/landing.css',
     ".page__lockup--deco { overflow: hidden; }",
     ".page__lockup--deco { }"),

    ("el abanico de /precios sale de su ranura",
     'resources/views/pages/pricing.blade.php',
     "<x-slot:deco><div class=\"rays pricing__rays\" aria-hidden=\"true\"></div></x-slot:deco>",
     "<div class=\"rays pricing__rays\" aria-hidden=\"true\"></div>"),

    ("la cabecera de /precios vuelve a 820 (el abanico encima del titular en escritorio)",
     'public/css/landing.css',
     ".page__head.pricing__head { max-width: none; }\n",
     ""),

    # ── Una página no estrena tipografía ──
    ("el titular de página sale de la declaración compartida",
     'public/css/landing.css',
     ".sec-head__title,\n.zones__title,\n.page__title {",
     ".sec-head__title,\n.zones__title {"),

    ("el rótulo de página sale de la declaración compartida",
     'public/css/landing.css',
     ".zones__eyebrow,\n.page__eyebrow {",
     ".zones__eyebrow {"),

    ("la entradilla de página sale de la declaración compartida",
     'public/css/landing.css',
     ".sec-head__lede,\n.page__lede {\n  font-size",
     ".sec-head__lede {\n  font-size"),

    ("vuelve el titular propio con la cara FALSIFICADA (el defecto REAL)",
     'public/css/site.css',
     ".page__head { max-width: 820px; }",
     ".page__head { max-width: 820px; }\n.page__title { font-weight: 800; font-stretch: 75%; }"),

    # ── El aire ──
    ("el hueco de arriba vuelve a depender de la ventana",
     'public/css/site.css',
     "    padding-top: calc(var(--nav-pad-block) + max(var(--nav-logo-h), var(--nav-btn-h)) + var(--sp-28));",
     "    padding-top: clamp(108px, 14vh, 156px);"),

    ("el aire de abajo vuelve a un literal fuera del sistema",
     'public/css/site.css',
     "    padding-bottom: var(--sec-air-mobile);",
     "    padding-bottom: 96px;"),
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
