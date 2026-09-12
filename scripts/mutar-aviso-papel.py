#!/usr/bin/env python3
"""Arnés de mutación del AVISO SOBRE PAPEL (`#564`, grieta 10 de la parada 05).

Cada mutación es un defecto REAL que alguien podría cometer al tocar los colores de estado. Si una
NO muerde, la guarda que debía cazarla no vale y hay que rehacerla — no bajar la mutación.

    docker compose exec -u sail -T laravel.test python3 scripts/mutar-aviso-papel.py

⚠️ Aquí NO hace falta reconstruir nada: todo lo que se muta es CSS servido tal cual, y las guardas
lo leen del disco. (Los arneses del cajón sí tocan `.vue` y dejan el bundle SSR rancio.)

⚠️ **El veredicto es el CÓDIGO DE SALIDA de `artisan test`**, nunca un `grep` de «passed»: un filtro
que no casa con ningún test sale con código 0 y diría que la mutación no muerde.
"""

import subprocess
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
CSS = 'public/css/site.css'
GUARDA = 'SemanticFillTextTest'

# (nombre, buscar, sustituir)
MUTACIONES = [
    ('el fondo de éxito vuelve a un hex y deja de seguir al verde del cliente',
     '--ok-bg: color-mix(in srgb, var(--ok) 14%, var(--bg));',
     '--ok-bg: #e7f6ec;'),

    ('el borde de error vuelve a un hex',
     '--err-border: color-mix(in srgb, var(--err) 30%, var(--bg));',
     '--err-border: #e3b5b0;'),

    ('el tinte se mezcla con BLANCO en vez de con la superficie, y sobre tinta sale claro',
     '--attn-bg: color-mix(in srgb, var(--attn) 14%, var(--bg));',
     '--attn-bg: color-mix(in srgb, var(--attn) 14%, #fff);'),

    ('«Pagado» vuelve a pintar el verde de marca sobre su propio tinte (2,64 medido)',
     '.orders__status--paid { background: var(--ok-bg); color: var(--fg); }',
     '.orders__status--paid { background: var(--ok-bg); color: var(--ok); }'),

    ('«Gratis» vuelve a hacerlo, y ése también sale en la landing',
     '.addons__badge--free { background: var(--ok-bg); color: var(--fg); border: 1px solid var(--ok-border); }',
     '.addons__badge--free { background: var(--ok-bg); color: var(--ok); border: 1px solid var(--ok-border); }'),

    ('vuelve el naranja del primer cliente al `:root`',
     '    --ok: #1f7a3d;',
     '    --warn: #b45309;\n    --ok: #1f7a3d;'),

    ('vuelve el color de reembolso, que la grieta 04 cerró sin color propio',
     '    --err: #c0392b;',
     '    --refund: #8a5a16;\n    --err: #c0392b;'),

    ('el historial vuelve a atenuarse con opacidad (la fecha a 3,10)',
     '.orders__item--past { background: var(--bg-soft); }',
     '.orders__item--past { opacity: 0.72; }'),

    # ⚠️ Estas dos son el error que la PROPIA tanda cometió y que solo vio la captura: `--money` vale
    # tinta por defecto, así que invertir el criterio no cambia nada en la suite ni en un clon sin
    # paquete — y con el del cliente deja «a pagar en el parque» en color y «Pagado» en tinta.
    ('el saldo PENDIENTE recupera el rol de cifra y se lee como una alarma',
     '.orders__balance--pay_online strong { color: var(--fg); }',
     '.orders__balance--pay_online strong { color: var(--money); }'),

    ('lo ya COBRADO pierde el rol de cifra, que es lo único a lo que le toca',
     '.orders__ledger--cash .orders__final strong { color: var(--money); }',
     '.orders__ledger--cash .orders__final strong { color: var(--fg); }'),
]


def corre_en_verde() -> bool:
    """`True` si la guarda pasa. El veredicto es el CÓDIGO DE SALIDA."""
    hecho = subprocess.run(
        ['php', 'artisan', 'test', '--filter', GUARDA],
        cwd=RAIZ, capture_output=True, text=True, check=False,
    )

    return hecho.returncode == 0


def main() -> int:
    # ⚠️ Sin verde de partida no se mide nada: una guarda ya roja «muerde» con cualquier mutación.
    print('· comprobando que la guarda sale VERDE antes de mutar…')
    if not corre_en_verde():
        print(f'✗ {GUARDA} ya está en ROJO con el árbol limpio. No se puede medir nada.')
        return 1

    ruta = RAIZ / CSS
    muerden = 0

    for nombre, buscar, sustituir in MUTACIONES:
        original = ruta.read_text()

        if buscar not in original:
            print(f'✗ {nombre}\n    el ancla no existe en {CSS} — la mutación no muta nada')
            continue

        ruta.write_text(original.replace(buscar, sustituir, 1))
        try:
            rojo = not corre_en_verde()
        finally:
            ruta.write_text(original)

        muerden += rojo
        print(f'{"✓" if rojo else "✗"} {nombre}')

    print(f'\n{muerden}/{len(MUTACIONES)} mutaciones muerden')

    return 0 if muerden == len(MUTACIONES) else 1


if __name__ == '__main__':
    sys.exit(main())
