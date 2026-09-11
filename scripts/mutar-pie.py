#!/usr/bin/env python3
"""Arnés de mutación de `#522` — el PIE del marco (tinta, destinos, idioma visible, colofón, vela)
y la VELA DEL MENÚ, que tenía el mismo defecto de línea de tiempo.

Mismo molde endurecido que `mutar-bandeja.py` (`#506`) y `mutar-destinos.py` (`#521`):
  · en PYTHON, y cada mutación verifica que el fichero CAMBIÓ antes de correr nada;
  · EXIGE VERDE antes de mutar (`#337`) y ÁRBOL LIMPIO en los ficheros que muta (`#181`);
  · restaura SIEMPRE, también si el proceso revienta (`#448`).

    python3 scripts/mutar-pie.py
"""
import subprocess
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
FILTRO = 'FooterFrameTest|FooterContactLinksTest|ArmazonContractTest|SurfaceScopeTest'
FICHEROS = [
    'resources/views/components/site/footer.blade.php',
    'resources/views/home.blade.php',
    'resources/views/components/layout.blade.php',
    'resources/views/components/site/cta-pair.blade.php',
    'public/css/landing.css',
    'public/css/site.css',
    'resources/js/ui/rail-sails.js',
]

# (nombre, fichero, texto que se busca, texto por el que se cambia)
MUTACIONES = [
    ("las interiores vuelven al PAPEL",
     'resources/views/components/site/footer.blade.php',
     "@props(['sections' => [], 'surface' => 'ink'])",
     "@props(['sections' => [], 'surface' => 'paper'])"),

    ("la portada vuelve a pedir el pie de TINTA (se funde con la tarjeta del cierre, #523)",
     'resources/views/home.blade.php',
     # ⚠️ Anclada a la LLAMADA entera: ` surface="paper" />` a secas aparece dos veces en la portada,
     # y una mutación ambigua no se aplica (lo dijo el propio arnés, «NO APLICADA»).
     '<x-site.footer :sections="$menuSections" surface="paper" />',
     '<x-site.footer :sections="$menuSections" />'),

    ("el pie recupera `.wrap` y la banda deja de ir a sangre",
     'resources/views/components/site/footer.blade.php',
     '<footer class="foot" data-surface=',
     '<footer class="foot wrap" data-surface='),

    # ── El arranque del par (#523): «Reservar» abierto, el registro plegado invitando ──
    ("el par vuelve a abrir la CUENTA a los visitantes (el arranque de #326)",
     'resources/views/components/layout.blade.php',
     '<body data-cta-mode="buy"',
     "<body data-cta-mode=\"{{ auth()->check() ? 'buy' : 'account' }}\""),

    ("la clase estática vuelve a pintar la cuenta abierta antes de Alpine",
     'resources/views/components/site/cta-pair.blade.php',
     "<div class=\"cta-pair {{ $s['racimo'] }}\"",
     "<div class=\"cta-pair {{ $s['racimo'] }}{{ auth()->check() ? '' : ' cta-pair--account' }}\""),

    ("las secciones de la portada se cuelan en el pie de las interiores",
     'resources/views/components/site/footer.blade.php',
     "        $sections,\n",
     "        $sections ?: \\App\\Domain\\Content\\Services\\SiteDestinations::homeSections(),\n"),

    ("la cuenta desaparece del pie",
     'resources/views/components/site/footer.blade.php',
     "        [['t' => __('landing.footer.account_link'), 'url' => route('account')]],\n",
     ""),

    ("el idioma pierde su nombre nativo (label in name)",
     'resources/views/components/site/footer.blade.php',
     "aria-label=\"{{ $langNames[$l] ?? strtoupper($l) }}\"",
     "aria-label=\"{{ __('landing.footer.language') }}\""),

    ("el idioma en curso deja de ir marcado",
     'resources/views/components/site/footer.blade.php',
     "@if (app()->getLocale() === $l) aria-current=\"true\" @endif",
     ""),

    ("el idioma vuelve a esconderse en un <noscript> (lo que el owner revirtió)",
     'resources/views/components/site/footer.blade.php',
     "<span class=\"foot__langs\" role=\"group\"",
     "<noscript><span class=\"foot__langs\" role=\"group\""),

    ("el colofón vuelve a llevar el lema",
     'resources/views/components/site/footer.blade.php',
     "<span>{{ $colofon }}</span>",
     "<span>{{ $colofon }} {{ $site['tagline'] ?? '' }}</span>"),

    ("sin ciudad, el colofón deja un «·» colgando",
     'resources/views/components/site/footer.blade.php',
     "if (! empty($site['city'])) {",
     "if (true) {"),

    ("la vela vuelve a la línea ANÓNIMA, que mira el documento y no la fila (el defecto REAL)",
     'public/css/landing.css',
     "    .foot__links-wrap[data-rail-scroll]::after { animation-timeline: --pie-destinos; }",
     "    .foot__links-wrap[data-rail-scroll]::after { animation-timeline: scroll(nearest inline); }"),

    ("la vela se pinta aunque la fila quepa",
     'public/css/landing.css',
     ".foot__links-wrap:not([data-rail-scroll])::after,\n.foot__legal-wrap:not([data-rail-scroll])::after { opacity: 0; animation: none; }\n",
     ""),

    ("rail-sails deja de publicar el hecho para las filas del pie",
     'resources/js/ui/rail-sails.js',
     "    ['.foot__links-wrap', '.foot__links'],\n",
     ""),

    ("en escritorio el contacto y lo legal vuelven a dos filas (el punto estático deja de caber)",
     'public/css/landing.css',
     "    .foot__legal-wrap { flex: 1 1 0; margin-top: var(--sp-6); padding-top: var(--sp-16); }\n",
     ""),

    ("la tinta de dentro del pie pierde su gris de CUERPO",
     'public/css/landing.css',
     "  --fg-body: var(--ink-fg-body);\n",
     ""),

    # ── #523: el pie de la portada sobre papel, con el cierre ──
    ("la tira del pie vuelve a asomar por el marco del cierre a pantalla completa",
     'public/css/landing.css',
     ".foot__strip { opacity: clamp(0, 1 - var(--cierre-q, 0) * 4, 1); }\n",
     ""),

    ("la tira lee la retirada del ARMAZÓN y desaparece ya en el punto estático",
     'public/css/landing.css',
     ".foot__strip { opacity: clamp(0, 1 - var(--cierre-q, 0) * 4, 1); }\n",
     ".foot__strip { opacity: var(--cierre-queda); }\n"),

    ("«Configuración de cookies» vuelve a llevar opacidad (3,7 : 1 sobre papel)",
     'public/css/site.css',
     "    cursor: pointer; text-decoration: none;\n}",
     "    cursor: pointer; opacity: .85; text-decoration: none;\n}"),

    ("la superficie de tinta vuelve a pisar la tinta del marcador del paquete (sello claro sobre amarillo)",
     'public/css/landing.css',
     "  --on-marker: var(--on-marker-brand, var(--fg));   /* la tinta del marcador: la del paquete si la declara, si no la de ESTA superficie (`#480`, `#523`) */",
     "  --on-marker: var(--fg);"),

    # ── La vela del MENÚ: el mismo defecto, en el eje de bloque ──
    ("la vela del MENÚ vuelve a la línea ANÓNIMA, que mira el `.menu` y no la lista (el defecto REAL)",
     'public/css/site.css',
     "            animation-timeline: --menu-lista;\n",
     "            animation-timeline: scroll(nearest block);\n"),

    ("la lista del menú deja de declarar su eje con nombre",
     'public/css/site.css',
     "    scroll-timeline-name: --menu-lista; scroll-timeline-axis: block;\n",
     ""),

    ("la vela del menú se pinta aunque la lista quepa",
     'public/css/site.css',
     "    .menu__inner:not([data-rail-scroll])::after { opacity: 0; }\n",
     ""),

    ("rail-sails deja de publicar el hecho para la lista del menú",
     'resources/js/ui/rail-sails.js',
     "    ['.menu__inner', '.menu__col-list', 'block'],\n",
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
