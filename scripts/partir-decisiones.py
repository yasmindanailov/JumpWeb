#!/usr/bin/env python3
"""
F1 del programa «producto e instancias» (`docs/specs/producto-e-instancias.md` §4.8, `DECISIONES #617`):
partir `docs/DECISIONES.md` (2,4 MB, 536 entradas) por CENTENAS en `docs/decisiones/NNN-NNN.md`, sin
reescribir una sola entrada.

    python3 scripts/partir-decisiones.py             plan y control, sin escribir
    python3 scripts/partir-decisiones.py --aplicar   escribe docs/decisiones/*.md (deja DECISIONES.md intacto:
                                                     el índice que lo sustituye se escribe a mano después)

Reglas:
  · Una entrada empieza en `## #N · ` y llega hasta la siguiente. Una línea `## ` que no sea cabecera de
    entrada (hay una: «## Y la segunda mitad…», dentro de una decisión) se queda dentro de su entrada.
  · Cada centena va a su fichero, y dentro del fichero las entradas van ordenadas por NÚMERO con orden
    estable: las bandas por carril (`CONVENCIONES §10.6`) las tenían entrelazadas por fecha, y buscar por
    número es la única forma en que se lee este registro. Los cuatro números duplicados (#217, #430,
    #431, #432, dos entradas cada uno) quedan adyacentes y se listan en el control.
  · CONTROL: las entradas escritas, vueltas a juntar en su ORDEN ORIGINAL, tienen que ser byte a byte el
    fichero de partida sin su cabecera. Si no, no se escribe nada.
"""
import argparse
import hashlib
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
ORIGEN = ROOT / 'docs/DECISIONES.md'
DESTINO = ROOT / 'docs/decisiones'
CABECERA = re.compile(r'^## #(\d+) · ')


def entradas(texto: str):
    """(cabecera_del_fichero, [(número, posición_original, texto_de_la_entrada)])"""
    lineas = texto.split('\n')
    inicios = [i for i, l in enumerate(lineas) if CABECERA.match(l)]
    if not inicios:
        sys.exit('✗ partir: no hay ninguna entrada `## #N ·` en DECISIONES.md')
    cabecera = '\n'.join(lineas[:inicios[0]]) + '\n'
    salida = []
    for k, i in enumerate(inicios):
        fin = inicios[k + 1] if k + 1 < len(inicios) else len(lineas)
        cuerpo = '\n'.join(lineas[i:fin])
        if k + 1 < len(inicios):
            cuerpo += '\n'
        salida.append((int(CABECERA.match(lineas[i]).group(1)), k, cuerpo))
    return cabecera, salida


def nombre(centena: int) -> str:
    return f'{centena * 100:03d}-{centena * 100 + 99:03d}.md'


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument('--aplicar', action='store_true')
    args = ap.parse_args()

    texto = ORIGEN.read_text(encoding='utf-8')
    cabecera, lista = entradas(texto)

    # Control 1: juntar en orden original reproduce el fichero sin cabecera.
    reconstruido = cabecera + ''.join(cuerpo for _, _, cuerpo in lista)
    if reconstruido != texto:
        sys.exit('✗ partir: la partición NO reproduce el original (control 1). No se escribe nada.')
    sha = hashlib.sha1(texto.encode('utf-8')).hexdigest()[:12]

    por_centena = {}
    for n, pos, cuerpo in lista:
        por_centena.setdefault(n // 100, []).append((n, pos, cuerpo))
    duplicados = sorted({n for n, _, _ in lista if sum(1 for m, _, _ in lista if m == n) > 1})

    print(f'DECISIONES.md: {len(texto):,} bytes · sha1 {sha} · {len(lista)} entradas · cabecera {len(cabecera)} bytes')
    print(f'{"fichero":<14} {"entradas":>8} {"rango":<14} {"bytes":>10}')
    for c in sorted(por_centena):
        e = por_centena[c]
        nums = [n for n, _, _ in e]
        print(f'{nombre(c):<14} {len(e):>8} #{min(nums)}…#{max(nums):<7} {sum(len(x) for _, _, x in e):>10,}')
    print(f'números duplicados (dos entradas cada uno, se conservan las dos): {duplicados or "ninguno"}')

    if not args.aplicar:
        print('→ plan solo; añade --aplicar para escribir docs/decisiones/*.md')
        return

    DESTINO.mkdir(exist_ok=True)
    existentes = sorted(p.name for p in DESTINO.glob('*.md'))
    if existentes:
        sys.exit(f'✗ partir: docs/decisiones/ ya tiene ficheros ({", ".join(existentes)}); no se sobrescribe')

    escritos = {}
    for c in sorted(por_centena):
        e = sorted(por_centena[c], key=lambda t: (t[0], t[1]))   # por número, estable
        nums = [n for n, _, _ in e]
        cab = (f'# Decisiones #{c * 100}–#{c * 100 + 99}\n\n'
               f'> Parte del registro de decisiones de JumpWeb: **`docs/DECISIONES.md` es el índice** y explica las bandas por\n'
               f'> carril. Las entradas van en orden de NÚMERO y una nueva se añade al final. Una decisión revertida lleva\n'
               f'> «Sustituida por #N (fecha)» en su primera línea; sin marca, sigue vigente. Se busca por número, no se lee de\n'
               f'> arriba abajo. Partido el 2026-09-16 desde el fichero único (`DECISIONES #617`) sin tocar una entrada: ese día\n'
               f'> tenía {len(e)} entradas, de #{min(nums)} a #{max(nums)}; el recuento vivo lo da `grep -cE \'^## #[0-9]+ ·\' docs/decisiones/{nombre(c)}`.\n\n')
        contenido = cab + ''.join(cuerpo for _, _, cuerpo in e)
        if not contenido.endswith('\n'):
            contenido += '\n'
        (DESTINO / nombre(c)).write_text(contenido, encoding='utf-8')
        escritos[nombre(c)] = e

    # Control 2: lo escrito, leído de disco, contiene cada entrada verbatim y en total las mismas.
    leidos = {p.name: p.read_text(encoding='utf-8') for p in DESTINO.glob('*.md')}
    faltan = [n for fichero, e in escritos.items() for n, _pos, cuerpo in e
              if cuerpo.rstrip('\n') not in leidos[fichero]]
    total = sum(len(v) for v in escritos.values())
    if faltan or total != len(lista):
        sys.exit(f'✗ partir: control 2 falla (faltan {faltan[:5]}, {total} de {len(lista)}). Revisa docs/decisiones/ antes de seguir.')
    print(f'✓ partir: {len(escritos)} ficheros escritos en docs/decisiones/ con las {total} entradas; control 1 y 2 en verde.')
    print('  Siguiente: escribir el índice en docs/DECISIONES.md a mano y correr scripts/docs-check.sh.')


if __name__ == '__main__':
    main()
