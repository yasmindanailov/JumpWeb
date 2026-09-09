#!/usr/bin/env python3
"""Extrae iconos del artboard «Iconos PJP» y los emite como componentes Blade del set.

⚠️⚠️ **ESTE GUION EXISTE PORQUE EL DE `#257` NO SE VERSIONÓ.** Aquella tanda trajo 43 dibujos «con
un guion desde el artboard, no transcritos a mano», pero el guion no entró en el repo — así que
`#475` tuvo que rehacerlo para traer trece más. Es el mismo papel que `scripts/kit-fachada.py`,
`logo-sombra.php` y `logo-letra-a.php`: sin él, la próxima ampliación del set vuelve a ser
«acuérdate de cómo se hizo».

⚠️ **Y la regla que lo justifica no es comodidad**: transcribir un asset desde el contexto de un
agente **ya corrompió un fichero en silencio** en este repo (`armazon-y-menu.md` §9.7: cabecera y
dimensiones válidas, 4.632 bytes de 6.900). La geometría se copia, no se teclea.

▶ **La fuente NO viaja en el repo** (`DECISIONES #1`): `mockup_playjumppark_v2/` está gitignorada.
Si no está, el guion lo dice y no inventa nada.

⚠️⚠️ **El `<g transform>` de la rejilla se DESCARTA, y está comprobado contra un icono ya extraído.**
El artboard dibuja cada glifo dos veces: en §01 con su geometría canónica y en §02 dentro de un
`<g transform="translate(12 12) scale(N) translate(-12 -12) …">` que **normaliza la cobertura
óptica para la rejilla de muestra**. `menu.blade.php`, que vino de `#257`, tiene los `rect` sin ese
envoltorio y con las mismas coordenadas: o sea que lo que se copia es el contenido, no el
envoltorio. Conservarlo escalaría los trece dibujos nuevos respecto de los cincuenta que ya están.

Uso:
    python3 scripts/extraer-iconos.py ui/tarta:tarta ui/cubo:soda-bucket …
    python3 scripts/extraer-iconos.py --lote            (los trece de `#475`)
"""
from __future__ import annotations

import pathlib
import re
import sys

RAIZ = pathlib.Path(__file__).resolve().parent.parent
ARTBOARD = RAIZ / 'mockup_playjumppark_v2' / 'Iconos PJP.dc.html'
DESTINO = RAIZ / 'resources' / 'views' / 'components' / 'icons'

# `código del artboard` → `nombre del componente`. El nombre es el del PRODUCTO, en inglés como el
# resto del set, no el rótulo del cliente: `ui/tapas` es un plato de picoteo en cualquier sector.
LOTE = {
    'ui/tarta': 'cake',
    'ui/cubo': 'ice-bucket',
    'ui/tapas': 'snacks',
    'ui/bebida': 'drink',
    'ui/hora-extra': 'clock-plus',
    'ui/horario': 'opening-hours',
    'ui/precios': 'price-tag',
    'ui/ofertas': 'offer',
    'ui/opiniones': 'reviews',
    'ui/invitaciones': 'invitations',
    'ui/foto': 'photo',
    'ui/pasos': 'steps',
    'ui/switch': 'toggle',
}

# ⚠️⚠️ **Se rellena con `replace`, JAMÁS con `str.format`**: `format` colapsa `{{` en `{`, así que el
# comentario salía como `{--` … `--}` — que **no abre un comentario Blade**, y entonces el docblock
# entero se renderiza como texto visible en la página. Es la trampa de `#298` por otra puerta, y la
# primera versión de este guion cayó en ella con los trece ficheros ya escritos.
CABECERA = """{{-- @ROTULO@ · `@CODIGO@` del artboard `Iconos PJP` (§02 SET UI).
     Rejilla **24**, área viva 20, masa mínima 3, **un solo color** por `currentColor`. Es la
     anatomía que declara el propio artboard (§01) y lo que §06 prohíbe romper: trazo por debajo
     de 3 y duotono dentro del glifo. Talla de trabajo, 24 (§05).
     ⚠️ El dibujo se **copia con un guion desde el artboard** (`scripts/extraer-iconos.py`), no se
     transcribe a mano: en este repo transcribir un asset desde el contexto ya corrompió un fichero
     en silencio (`armazon-y-menu.md` §9.7).
     ▶ Del lote de trece que cerró el set en `DECISIONES #475`. Si este dibujo cambia, la copia
     del cajón tiene que cambiar con él (`SidebarIconParityTest`).
--}}
"""

# El `<g>` de normalización de la rejilla de muestra: se descarta conservando su contenido.
G_REJILLA = re.compile(
    r'<g transform="translate\(12 12\) scale\([\d.]+\) translate\(-12 -12\)'
    r'(?: translate\([-\d. ]+\))?">(.*?)</g>',
    re.S,
)


def svg_de(codigo: str, html: str) -> tuple[str, str]:
    """Devuelve (atributos del <svg>, contenido) del icono con ese código."""
    marca = f'>{codigo}</div>'
    fin = html.find(marca)
    if fin == -1:
        raise SystemExit(f'✗ «{codigo}» no está en el artboard: no se inventa nada.')

    # El <svg> de la tarjeta es el último que abre ANTES del código.
    ini = html.rfind('<svg ', 0, fin)
    cierre = html.find('</svg>', ini)
    if ini == -1 or cierre == -1:
        raise SystemExit(f'✗ «{codigo}» no tiene <svg> delante: el localizador mira al vacío.')

    bruto = html[ini:cierre + 6]
    rotulo = re.search(r'line-height:1\.2;">([^<]+)</div>', html[cierre:fin])

    cabeza = re.match(r'<svg ([^>]*)>', bruto)
    if cabeza is None:
        raise SystemExit(f'✗ «{codigo}»: no se ha podido leer la cabecera del <svg>.')

    cuerpo = bruto[cabeza.end():-6]
    cuerpo = G_REJILLA.sub(lambda m: m.group(1), cuerpo)

    # De los atributos se conserva lo que es del DIBUJO (fill/stroke y sus modificadores) y se tira
    # lo que es de la MUESTRA (width/height de la rejilla del artboard): la talla la pone el
    # consumidor por `$attributes`, que es como están los cincuenta que ya vinieron.
    attrs = [
        a for a in re.findall(r'[\w-]+="[^"]*"', cabeza.group(1))
        if not a.startswith(('width=', 'height=', 'style=', 'viewBox='))
    ]

    return ' '.join(attrs), cuerpo.strip(), (rotulo.group(1) if rotulo else codigo)


def sangrar(cuerpo: str) -> str:
    """Un elemento por línea, con la sangría del resto del set."""
    piezas = re.findall(r'<(?:path|rect|circle|ellipse|line|polygon|polyline|g|use)\b[^>]*/?>|</g>', cuerpo)
    if not piezas:
        return '    ' + cuerpo
    return '\n'.join('    ' + p.replace('></path>', ' />').replace('>', ' />', 1)
                     if p.endswith('></path>') else '    ' + p for p in piezas)


def main() -> None:
    if not ARTBOARD.is_file():
        raise SystemExit(
            f'✗ no está el artboard en «{ARTBOARD}».\n'
            '  La copia del canvas está gitignorada (`DECISIONES #1`): bájala con DesignSync antes.'
        )

    args = sys.argv[1:]
    if args == ['--lote']:
        pares = LOTE
    elif args:
        pares = dict(a.split(':', 1) for a in args)
    else:
        raise SystemExit(__doc__)

    html = ARTBOARD.read_text(encoding='utf-8')

    for codigo, nombre in pares.items():
        attrs, cuerpo, rotulo = svg_de(codigo, html)
        destino = DESTINO / f'{nombre}.blade.php'

        cuerpo_limpio = re.sub(r'></(?:path|rect|circle|ellipse|line|polygon|polyline)>', ' />', cuerpo)
        # Un elemento por línea, como los cincuenta que ya estaban: un `path` detrás de otro en la
        # misma línea hace ilegible el diff cuando el artboard corrige un dibujo.
        cuerpo_limpio = re.sub(r'/>\s*<', '/>\n    <', cuerpo_limpio.strip())
        cuerpo_limpio = re.sub(r'\s*\n\s*', '\n    ', cuerpo_limpio)

        destino.write_text(
            CABECERA.replace('@ROTULO@', rotulo).replace('@CODIGO@', codigo)
            + f"<svg {{{{ $attributes->merge(['width' => 24, 'height' => 24]) }}}} viewBox=\"0 0 24 24\" {attrs}\n"
            + '     aria-hidden="true" focusable="false">\n'
            + '    ' + cuerpo_limpio + '\n'
            + '</svg>\n',
            encoding='utf-8',
        )
        print(f'  ✓ {codigo:20s} → resources/views/components/icons/{nombre}.blade.php')

    print(f'\n{len(pares)} icono(s) extraído(s).')


if __name__ == '__main__':
    main()
