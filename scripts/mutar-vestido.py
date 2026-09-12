#!/usr/bin/env python3
"""Arnés de mutación de la pasada de vestido (`#537`).

Cada mutación es el DEFECTO REAL que motivó su caso, no un cambio cualquiera: una guarda no se
sabe si sirve hasta que se muta con el fallo que la hizo nacer. Exige VERDE antes de empezar
(la lección de `#335`: un filtro que no casa con ningún test sale con código != 0 y miente).
"""
import subprocess, sys, pathlib

RAIZ = pathlib.Path(__file__).resolve().parent.parent
FILTRO = 'IdentityTint|DudasSection'
FICHEROS = ['public/css/site.css', 'public/css/landing.css']

MUTACIONES = [
    ('site.css', '--identity-fill: var(--strip-1, #2F7FA8);', '--identity-fill: #1AA9DE;',
     'R0 · clavar el cian de ESTE cliente en el producto (adiós white-label)'),
    ('site.css', '--tint-info:        color-mix(in srgb, var(--identity-fill) 14%, var(--bg));',
     '--tint-info:        #D5EAEE;',
     'R0 · tinte tecleado en vez de derivado'),
    ('landing.css', 'color: var(--interactive);\n  display: inline-flex; align-items: center; gap: var(--sp-10);',
     'color: var(--fg-mute);\n  display: inline-flex; align-items: center; gap: var(--sp-10);',
     'R1 · el rótulo vuelve al gris: la identidad desaparece de las 7 páginas'),
    ('landing.css', 'background: var(--identity-fill);\n}', 'background: var(--fg);\n}',
     'R1 · el filete deja de ser identidad'),
    ('landing.css',
     '     Sobre una superficie teñida solo aguantan la tinta (14,8) y el Azul Muro (5,68). */\n  color: var(--fg);',
     '     Sobre una superficie teñida solo aguantan la tinta (14,8) y el Azul Muro (5,68). */\n  color: var(--fg-mute);',
     'R2 · Humo sobre el tinte: 4,40, por debajo de AA'),
    ('landing.css', '  color: var(--interactive);\n}\n.before__row-val', '  color: var(--fg-mute);\n}\n.before__row-val',
     'R3 · la clave de la fila pierde la identidad'),
    ('landing.css', 'border: 1px solid var(--tint-ok-border);\n  border-radius: var(--r-lg);\n  padding: var(--sp-18);',
     'border: 1px solid var(--line);\n  border-radius: var(--r-lg);\n  padding: var(--sp-18);',
     'R4 · la tarjeta de reseña pierde el borde por donde recibe color'),
    ('site.css', 'background: var(--identity-deep); color: var(--paper-bg);',
     'background: var(--identity-deep); color: var(--bg);',
     'CTA · `--bg` en vez de `--paper-bg`: dentro del hero el rótulo cae a 3,55'),
    ('site.css', 'background: var(--identity-deep); color: var(--paper-bg);',
     'background: var(--fg); color: var(--paper-bg);',
     'CTA · vuelve a tinta: el secundario grita 6,3× más que la acción'),
    ('landing.css', 'background: var(--tint-attn);\n  border: 1px solid var(--tint-attn-border);',
     'background: var(--bg-card);\n  border: 1px solid var(--line);',
     'Dudas · la tarjeta pierde su tinte'),
]

def corre():
    r = subprocess.run(['docker', 'compose', 'exec', '-u', 'sail', '-T', 'laravel.test',
                        'php', 'artisan', 'test', '--filter', FILTRO],
                       cwd=RAIZ, capture_output=True, text=True,
                       # ⚠️ `errors='replace'`: la salida de PHPUnit se trunca y puede partir un
                       # carácter multibyte por la mitad — el arnés reventaba con UnicodeDecodeError
                       # DESPUÉS de mutar, y solo el `finally` salvó el árbol.
                       errors='replace')
    return r.returncode

print('· comprobando que el árbol está VERDE antes de mutar…')
if corre() != 0:
    sys.exit('✗ la suite YA falla sin mutar: el arnés no diría nada. Arregla eso primero.')
print('  ✓ verde\n')

originales = {f: (RAIZ / f).read_text() for f in FICHEROS}
muerden = 0
try:
    for i, (fichero, viejo, nuevo, que) in enumerate(MUTACIONES, 1):
        ruta = RAIZ / ('public/css/' + fichero)
        txt = ruta.read_text()
        if viejo not in txt:
            print(f'{i:2}. ✗ NO APLICABLE — el patrón no existe: {que}')
            continue
        ruta.write_text(txt.replace(viejo, nuevo, 1))
        rc = corre()
        ruta.write_text(txt)
        if rc != 0:
            muerden += 1
            print(f'{i:2}. ✓ muerde   · {que}')
        else:
            print(f'{i:2}. ✗ NO MUERDE · {que}')
finally:
    for f, t in originales.items():
        (RAIZ / f).write_text(t)

print(f'\n  {muerden}/{len(MUTACIONES)} mutaciones detectadas')
sys.exit(0 if muerden == len(MUTACIONES) else 1)
