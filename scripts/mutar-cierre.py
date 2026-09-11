#!/usr/bin/env python3
"""Arnés de mutación de `#526` — el CIERRE de las páginas interiores (T3a·4): la tarjeta dentro de la
banda de tinta del pie, sin juego, sin eslogan y con un solo botón; la regla de «Reservar» compartida
con la portada; y la tarjeta a fila entera del pie en escritorio (el defecto que midió la sonda).

Mismo molde endurecido que `mutar-cabecera.py` (`#525`) y `mutar-pie.py` (`#522`):
  · en PYTHON, y cada mutación verifica que el fichero CAMBIÓ antes de correr nada;
  · EXIGE VERDE antes de mutar (`#337`) y ÁRBOL LIMPIO en los ficheros que muta (`#181`);
  · restaura SIEMPRE, también si el proceso revienta (`#448`).

    python3 scripts/mutar-cierre.py
"""
import subprocess
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
FILTRO = 'PageClosingTest|MenuGroupsTest|SaltaJuegoTest|SectionHeadlineTest|FooterFrameTest'
PIE = 'resources/views/components/site/footer.blade.php'
TARJETA = 'resources/views/components/site/closing.blade.php'
CUERPO = 'resources/views/components/site/closing-body.blade.php'
FICHEROS = [
    PIE, TARJETA, CUERPO,
    'resources/views/home.blade.php',
    'resources/views/pages/rules.blade.php',
    'resources/views/pages/text.blade.php',
    'public/css/site.css',
    'public/css/landing.css',
]

# El comentario que va entre el `<footer>` y el cierre, tal cual, para poder mover el bloque entero.
COMENTARIO = (
    "        {{-- ══ EL CIERRE DE LAS INTERIORES VA DENTRO DE LA BANDA (`#526`, T3a·4) ═══════════════════\n"
    "             Como lo dibuja `Layout Paginas PJP`: una sola banda de tinta que empieza con la tarjeta y\n"
    "             sigue con el pie. Lo piden las páginas del inventario (`closing`); la portada no, porque\n"
    "             lleva el suyo, y las legales y las pantallas de servicio tampoco.\n"
    "             ▶ Y dentro de la banda la barra de móvil se retira sola al llegar el cierre: se aparta\n"
    "             cuando entra `.foot`, y el cierre ya es `.foot`. --}}\n"
)
APERTURA = "<footer class=\"foot\" data-surface=\"{{ $surface === 'paper' ? 'paper' : 'ink' }}\">\n    <div class=\"foot__inner wrap\">\n"
BLOQUE = "        @if ($closing)\n            <x-site.closing />\n        @endif\n"

# (nombre, fichero, texto que se busca, texto por el que se cambia)
MUTACIONES = [
    # ── Dónde sale ──
    ("el pie deja de pintar el cierre",
     PIE, BLOQUE, ""),

    ("el cierre sale de la banda y queda sobre el papel, antes del pie (la opción B, descartada)",
     PIE,
     APERTURA + COMENTARIO + BLOQUE,
     "@if ($closing)\n    <x-site.closing />\n@endif\n" + APERTURA + COMENTARIO),

    ("/normas deja de pedir el cierre",
     'resources/views/pages/rules.blade.php',
     "<x-site.footer :closing=\"true\" />",
     "<x-site.footer />"),

    ("una página legal pide el cierre (no es del inventario)",
     'resources/views/pages/text.blade.php',
     "<x-site.footer />",
     "<x-site.footer :closing=\"true\" />"),

    # ── Lo que el owner quitó en las interiores ──
    ("la tarjeta de las interiores arrastra el eslogan",
     TARJETA,
     "<x-site.closing-body />",
     "<x-site.closing-body :slogan=\"true\" />"),

    ("la tarjeta de las interiores vuelve a ofrecer «Llamar»",
     TARJETA,
     "<x-site.closing-body />",
     "<x-site.closing-body :call=\"true\" />"),

    ("el cuerpo ignora la marca de «Llamar» y lo pinta siempre que hay teléfono",
     CUERPO,
     "@if ($call && $site['has_phone'])",
     "@if ($site['has_phone'])"),

    # ── La portada no pierde lo suyo ──
    ("la portada pierde el eslogan del cierre",
     'resources/views/home.blade.php',
     "<x-site.closing-body :slogan=\"true\" :call=\"true\" />",
     "<x-site.closing-body :call=\"true\" />"),

    # ── La regla de «Reservar», compartida ──
    ("con la venta cerrada y sin teléfono, «Reservar» lleva a otro sitio",
     CUERPO,
     ": route('precios'))",
     ": route('home'))"),

    # ── La tarjeta quieta ──
    ("la tarjeta vuelve a medir «el hueco que deja el pie»",
     'public/css/site.css',
     ".reserve--pagina .reserve__box {\n    min-height: 0;\n",
     ".reserve--pagina .reserve__box {\n"),

    ("tinta sobre tinta sin filete",
     'public/css/site.css',
     "    box-shadow: none;\n    border: 1px solid var(--line);\n",
     "    box-shadow: none;\n"),

    ("la tarjeta hereda el hueco del minijuego",
     'public/css/site.css',
     "    --salta-hueco: calc(clamp(20px, 3vw, 40px) + clamp(72px, 8vw, 108px) * 197 / 377 + var(--sp-16));\n",
     ""),

    ("en escritorio la tarjeta deja de ocupar la fila entera del pie (el defecto REAL: 610 de 1120)",
     'public/css/landing.css',
     "    .reserve--pagina,\n    .foot__colophon { flex: 0 0 100%; }",
     "    .foot__colophon { flex: 0 0 100%; }"),
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
