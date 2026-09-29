#!/usr/bin/env python3
"""Genera el sprite `client-kit.svg` del 2.º cliente desde el artboard «Elementos Fachada».

⚠️⚠️ **ESTE GUION EXISTE PORQUE NI EL KIT NI SU FUENTE VIAJAN EN EL REPO.** `public/img/client-kit.svg`
está gitignorado (es el paquete de la instalación) y las copias del canvas también (`DECISIONES #1`:
este repo es el producto sin marca de cliente). Sin él, la reconstrucción de los símbolos sería
«acuérdate de cómo se hizo», que es como `#302` dejó un kit que nadie podía rehacer.
Mismo papel que `scripts/logo-sombra.php` y `scripts/logo-letra-a.php`.

❗❗❗ **LEE `mockup_playjumppark_v2/`, Y LA VERSIÓN ANTERIOR LEÍA LA OTRA.** Medido el 2026-09-12:
`mockup_playjumppark/` es **el ARCHIVO** (`#469`) y todavía trae el **grupo D entero** —D1 a D8, las
siluetas que `[DECIDIDO owner, 2026-08-30]` retiró— además de `E1`; `v2` no los trae. O sea que la
copia que este guion leía es la de ANTES de la decisión del owner. Hoy no extraía ninguna pieza de
ese grupo, así que no llegó a colarse arte retirado — pero cualquier familia nueva que alguien
añadiera aquí lo habría hecho **sin que nada fallara**.
    archivo  55 <svg> · grupos A1…G6 **+ D1–D8 + E1**
    v2       48 <svg> · grupos A1…G6 **sin D**            ← la decisión del owner, aplicada

⚠️ La geometría se COPIA con un guion, no se transcribe (la regla de `#257`): transcribir a mano
un `<path>` de 1.000 caracteres es un error silencioso esperando a que alguien mire el dibujo.

⚠️⚠️ El kit NO puede traer atributos de presentación (`IllustrationKit::FORBIDDEN_ATTRS`): en el
artboard el color vive en el `style` del `<svg>` contenedor, así que se extrae SOLO el contenido
del `<g>`/`<path>` interior, que ya viene limpio. El guion lo COMPRUEBA antes de escribir.

⚠️ **Los símbolos `zone-*` NO se generan aquí y se CONSERVAN**: son los dibujos de cada zona del
parque, que no salen de este artboard. Si el kit instalado los trae, pasan tal cual.

❗ **Qué dibujo va en qué ranura es decisión EDITORIAL de la instalación**, no del producto: el
producto declara DÓNDE hay hueco (`IllustrationKit::SLOTS`) y el paquete decide QUÉ dibujo cae ahí.

❗❗ **Desde la T6g (29-09) solo emite las CUATRO poses del arco** (`<x-site.arc>`, en la portada del
ANFITRIÓN del producto): el mural de la web vieja, sus manchas, el trío, los iconos y el laboratorio
se retiraron con su última pantalla (`specs/isla-y-landing-nueva.md` §4.25). Las demás piezas del
artboard siguen ahí; volver a emitir una es declarar antes su ranura con su consumidor.

Uso:  python3 scripts/kit-fachada.py && php artisan kit:build --check
"""
import re
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
ART = RAIZ / "mockup_playjumppark_v2/Elementos Fachada.dc.html"
KIT = RAIZ / "public/img/client-kit.svg"

PROHIBIDOS = ['fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin',
              'stroke-dasharray', 'stroke-dashoffset', 'style', 'color', 'opacity',
              'fill-opacity', 'stroke-opacity']

# ── LAS RANURAS QUE SE EMITEN · las del arco (`IllustrationKit::SLOTS`) ───────────────────────
# Las cuatro poses de adulto que pinta `<x-site.arc>`. Las manchas del reparto, las del laboratorio,
# las otras ocho poses y el trío se fueron con la T6g: una clave de más tumba `kit:build`.
ARCO = {'slot-pose-p2', 'slot-pose-p5', 'slot-pose-p8', 'slot-pose-p9'}


def limpio(fragmento: str, donde: str) -> str:
    """Revienta si el fragmento trae un atributo de presentación. No sanea: avisa."""
    for a in PROHIBIDOS:
        if re.search(r'(?<![-\w])' + re.escape(a) + r'\s*=', fragmento):
            sys.exit(f"✗ {donde}: trae `{a}`. El kit se rechazaría entero.")
    return fragmento


def poses(art: str) -> list[tuple[str, str, str, str]]:
    """
    Las DOCE poses (9 de adulto + 3 de niño), con el nombre que les da el artboard.

    ⚠️⚠️ **El rótulo va DESPUÉS del dibujo, no antes**, y por eso el mapeo «el `<svg>` más cercano»
    sale desplazado UNO: con él, `abierto` se quedaba con la figura de `picado` y las dos últimas de
    cada grupo apuntaban al dibujo de otra sección. Cada rótulo se empareja con el `<svg>` que lo
    PRECEDE, y el guion comprueba que salen **doce `viewBox` distintos**: si el artboard cambia su
    maquetación, eso es lo que lo pone en rojo en vez de escribir doce veces la misma figura.

    ⚠️ **Se sacan del ARTBOARD y no de `assets/pose-*.svg`**, aunque los ficheros sueltos existan:
    medido en `#281`, los sueltos traen `fill="#000"` (7 de 7) y el artboard no (1 de 138, y vale
    `none`). Un `fill` propio haría que `kit:build` rechazara el kit entero — y con razón, porque
    ganaría al color que pone el producto.

    @return (clave, viewBox, título, cuerpo)
    """
    svgs = [(m.start(), m.group(1), m.group(2)) for m in
            re.finditer(r'<svg[^>]*viewBox="([^"]+)"[^>]*>\s*<path[^>]*\sd="([^"]{40,})"', art, re.S)]

    rotulos = [(m.start(), m.group(1).lower(), m.group(2)) for m in
               re.finditer(r'>([PK]\d)<br>([a-záéíóúñ]+)<', art, re.I)]

    if len(rotulos) != 12:
        sys.exit(f"✗ Esperaba 12 poses rotuladas y he encontrado {len(rotulos)}.")

    salida, cajas = [], set()

    for pos, codigo, nombre in rotulos:
        previos = [s for s in svgs if s[0] < pos]
        if not previos:
            sys.exit(f"✗ La pose «{codigo} {nombre}» no tiene ningún dibujo delante.")
        _, vb, d = previos[-1]
        cajas.add(vb)
        salida.append((f'slot-pose-{codigo}', vb, f'Silueta · {nombre}', f'<path d="{d}"></path>'))

    if len(cajas) != 12:
        sys.exit(f"✗ Las 12 poses comparten solo {len(cajas)} `viewBox`: el emparejamiento se ha "
                 "desplazado. El rótulo va DESPUÉS de su dibujo.")

    return salida


def main() -> int:
    if not ART.is_file():
        sys.exit(f"✗ No está la copia del canvas: {ART}\n  Bájala con `DesignSync` antes de correr esto.")

    art = ART.read_text(encoding='utf-8', errors='replace')

    # ── Los dibujos de ZONA se conservan: no salen de este artboard ──────────────────────────
    previos: list[str] = []
    if KIT.is_file():
        previos = [s for s in re.findall(r'(<symbol\b.*?</symbol>)', KIT.read_text(encoding='utf-8'), re.S)
                   if re.search(r'id="zone-', s)]

    # ⚠️⚠️ **Solo se emiten las ranuras DECLARADAS.** `kit:build --check` rechaza el kit entero si
    # trae un `slot-*` que el producto no declara, y con razón: una clave de más es un dibujo que
    # nadie pinta y que nadie echa de menos. Las doce poses se extraen igual (su emparejamiento con
    # el rótulo es la parte frágil y es lo que avisa si el artboard cambia); se emiten las del arco.
    nuevos = [p for p in poses(art) if p[0] in ARCO]

    salida = ['<svg xmlns="http://www.w3.org/2000/svg">']
    salida += ['  ' + p for p in previos]
    for clave, vb, titulo, cuerpo in nuevos:
        limpio(cuerpo, clave)
        salida.append(f'  <symbol id="{clave}" viewBox="{vb}"><title>{titulo}</title>{cuerpo}</symbol>')
    salida.append('</svg>')

    KIT.write_text('\n'.join(salida) + '\n', encoding='utf-8')
    print(f"✓ kit escrito: {len(previos)} de zona (conservados) + {len(nuevos)} del artboard "
          f"= {len(previos) + len(nuevos)} símbolos · {KIT.stat().st_size} B")
    return 0


if __name__ == '__main__':
    sys.exit(main())
