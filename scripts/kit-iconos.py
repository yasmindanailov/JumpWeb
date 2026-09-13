#!/usr/bin/env python3
"""Genera los ICONOS del kit de la instalación (`client-kit.svg`) desde el artboard «Iconos PJP».

`DECISIONES #582` · pasada de vestido, eje de iconos. Hermano de `scripts/kit-fachada.py` (las
manchas y las poses) y de `scripts/extraer-iconos.py` (el set de 24 del PRODUCTO). Existe por el
mismo motivo que los dos: **ni el kit ni su fuente viajan en el repo** (`DECISIONES #1`), y sin guion
la reconstrucción sería «acuérdate de cómo se hizo».

Dos familias, las dos ARTE DE ESTE PARQUE y por eso por el hueco por instalación (`#286`):
  · **las pegatinas de ZONA** (`zone-<slug>`, rejilla 64, §07 del artboard) — `[DECIDIDO owner]`: van
    POR ZONA del parque, no por atracción. Qué ficha del artboard le toca a qué zona es EDITORIAL de
    la instalación y vive en la tabla `ZONAS`.
  · **los «Del parque»** (`slot-ico-*`, rejilla 24, §02) — cada uno entra el día que tiene pantalla;
    una ranura sin consumidor tumba la validación del kit.

❗❗❗ **LOS TRAZOS SE CONVIERTEN A RELLENO, y es la mitad cara de este guion.** Los glifos del artboard
mezclan relleno y trazo (`stroke-width` 8 en la rejilla de 64, 3 en la de 24, con remates redondos),
y el kit PROHÍBE `fill`, `stroke` y compañía en sus símbolos (`IllustrationKit::FORBIDDEN_ATTRS`):
en un `<use>` el atributo del original gana al color del producto, en silencio. Así que cada trazo se
reescribe como la geometría que pinta:
    segmento recto   → rectángulo de su grosor + un círculo en cada extremo (remate y unión redondos)
    curva            → polígono desplazado a los dos lados, muestreado, + los dos remates
    rect / círculo   → el mismo con el grosor sumado (relleno + trazo) o un anillo `evenodd` (solo trazo)
    máscara (huecos) → un solo `<path fill-rule="evenodd">` con los huecos como subtrazos
⚠️ Cada pieza va como elemento SUELTO y no fundida en un trazado: dos subtrazos con sentidos de giro
opuestos se CANCELAN con la regla `nonzero`, y un hueco inventado no falla, se ve.
▶ Se VERIFICA píxel a píxel contra el original (`storage/app/shots/iconos-cmp.html`, que este guion
escribe): la conversión no se da por buena porque la aritmética cuadre.

⚠️⚠️ **El `<g transform>` de la rejilla de muestra se DESCARTA** en los de 24, como en
`extraer-iconos.py`. Medido aquí además: en `ui/saltador` es `scale(3.4483)` y con él el glifo sale
DESBORDADO de su caja; sin él, entero. Es presentación del artboard, no geometría.

Uso:  python3 scripts/kit-iconos.py && php artisan kit:build --check
"""
from __future__ import annotations

import math
import re
import sys
import xml.etree.ElementTree as ET
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
ART = RAIZ / 'mockup_playjumppark_v2' / 'Iconos PJP.dc.html'
KIT = RAIZ / 'public' / 'img' / 'client-kit.svg'
CMP = RAIZ / 'storage' / 'app' / 'shots' / 'iconos-cmp.html'

# ── EL REPARTO ────────────────────────────────────────────────────────────────────────────────
# (clave del kit, nombre de la ficha en §07 del artboard, título del símbolo)
# ⚠️ El COLOR de la pegatina NO sale de aquí: es `zones.color`, el mismo dato que ya nombra a la zona
# en el resto de la web. El artboard pinta por familia; en este producto el color de una zona lo pone
# el panel, y que la pegatina lo repita es lo que su regla pide («el color ES el nombre de la zona»).
ZONAS = [
    ('zone-jump', 'Camas elásticas', 'Zona Jump · camas elásticas'),
    ('zone-kids', 'Zona mini', 'Zona Kids · zona mini'),
    ('zone-cumpleanos', 'Cumpleaños', 'Cumpleaños · gorro de fiesta'),
]
# (clave del kit, código del artboard, título del símbolo)
DEL_PARQUE = [
    ('slot-ico-calcetines', 'ui/calcetines', 'Calcetines antideslizantes'),
    ('slot-ico-altura', 'ui/altura', 'Altura mínima'),
    ('slot-ico-saltador', 'ui/saltador', 'Saltador'),
    ('slot-ico-cama', 'ui/cama', 'Cama elástica'),
    ('slot-ico-canasta', 'ui/canasta', 'Canasta'),
    ('slot-ico-bote', 'ui/bote', 'Bote'),
]
# ⚠️ Solo se emiten las que tienen PANTALLA: una clave que `IllustrationKit::SLOTS` no declara hace
# que `kit:build` rechace el kit ENTERO. Añadir aquí es añadir allí, con su consumidor, en el mismo cambio.
# ▶ Con pantalla hoy (`[DECIDIDO owner, 2026-09-13]`): calcetines en «Antes de venir», altura en «La
#   altura, de un vistazo» y el saltador en «Mientras saltas», los dos en `/normas`. Cama, canasta y
#   bote ESPERAN a tener pantalla: por zona del parque no tienen ninguna que sea suya.
EMITIR = ['zone-jump', 'zone-kids', 'zone-cumpleanos',
          'slot-ico-calcetines', 'slot-ico-altura', 'slot-ico-saltador']

PROHIBIDOS = ['fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin',
              'stroke-dasharray', 'stroke-dashoffset', 'style', 'color', 'opacity',
              'fill-opacity', 'stroke-opacity']

MUESTRAS_CURVA = 24


def n(v: float) -> str:
    s = f'{v:.3f}'.rstrip('0').rstrip('.')
    return '0' if s in ('-0', '') else s


# ── GEOMETRÍA ─────────────────────────────────────────────────────────────────────────────────
def d_circulo(cx: float, cy: float, r: float) -> str:
    return f'M{n(cx - r)} {n(cy)}a{n(r)} {n(r)} 0 1 0 {n(2 * r)} 0a{n(r)} {n(r)} 0 1 0 {n(-2 * r)} 0z'


def d_rrect(x: float, y: float, w: float, h: float, rx: float) -> str:
    rx = max(0.0, min(rx, w / 2, h / 2))
    if rx == 0:
        return f'M{n(x)} {n(y)}H{n(x + w)}V{n(y + h)}H{n(x)}z'
    # Recorrido en sentido HORARIO en pantalla: con `sweep=1` las esquinas abomban hacia fuera.
    return (f'M{n(x + rx)} {n(y)}H{n(x + w - rx)}A{n(rx)} {n(rx)} 0 0 1 {n(x + w)} {n(y + rx)}'
            f'V{n(y + h - rx)}A{n(rx)} {n(rx)} 0 0 1 {n(x + w - rx)} {n(y + h)}'
            f'H{n(x + rx)}A{n(rx)} {n(rx)} 0 0 1 {n(x)} {n(y + h - rx)}'
            f'V{n(y + rx)}A{n(rx)} {n(rx)} 0 0 1 {n(x + rx)} {n(y)}z')


def circulo(cx, cy, r) -> str:
    return f'<circle cx="{n(cx)}" cy="{n(cy)}" r="{n(r)}"/>'


def poligono(puntos) -> str:
    return '<path d="M' + 'L'.join(f'{n(x)} {n(y)}' for x, y in puntos) + 'z"/>'


def segmento(a, b, r) -> list[str]:
    """Un trazo recto de remate redondo: el rectángulo de su grosor y un círculo en cada punta."""
    (x1, y1), (x2, y2) = a, b
    largo = math.hypot(x2 - x1, y2 - y1)
    piezas = [circulo(x1, y1, r), circulo(x2, y2, r)]
    if largo > 1e-9:
        nx, ny = -(y2 - y1) / largo * r, (x2 - x1) / largo * r
        piezas.append(poligono([(x1 + nx, y1 + ny), (x2 + nx, y2 + ny), (x2 - nx, y2 - ny), (x1 - nx, y1 - ny)]))
    return piezas


def curva(p0, p1, p2, p3, r) -> list[str]:
    """Una Bézier cúbica con grosor: polígono desplazado a los dos lados + los dos remates."""
    def punto(t):
        u = 1 - t
        return (u ** 3 * p0[0] + 3 * u * u * t * p1[0] + 3 * u * t * t * p2[0] + t ** 3 * p3[0],
                u ** 3 * p0[1] + 3 * u * u * t * p1[1] + 3 * u * t * t * p2[1] + t ** 3 * p3[1])

    def tangente(t):
        u = 1 - t
        return (3 * u * u * (p1[0] - p0[0]) + 6 * u * t * (p2[0] - p1[0]) + 3 * t * t * (p3[0] - p2[0]),
                3 * u * u * (p1[1] - p0[1]) + 6 * u * t * (p2[1] - p1[1]) + 3 * t * t * (p3[1] - p2[1]))

    izq, der = [], []
    for i in range(MUESTRAS_CURVA + 1):
        t = i / MUESTRAS_CURVA
        (x, y), (tx, ty) = punto(t), tangente(t)
        largo = math.hypot(tx, ty) or 1.0
        nx, ny = -ty / largo * r, tx / largo * r
        izq.append((x + nx, y + ny))
        der.append((x - nx, y - ny))
    return [poligono(izq + der[::-1]), circulo(*p0, r), circulo(*p3, r)]


# ── LECTURA DE UN TRAZADO ─────────────────────────────────────────────────────────────────────
TOKEN = re.compile(r'[MmLlHhVvCcSsZz]|-?(?:\d+\.?\d*|\.\d+)(?:[eE][-+]?\d+)?')


def tramos(d: str):
    """Recorre un `d` y devuelve sus tramos: ('L', a, b) · ('C', p0, p1, p2, p3). Solo lo que usan los glifos."""
    tok = TOKEN.findall(d)
    i, cmd = 0, None
    cur = inicio = (0.0, 0.0)
    ultimo_ctrl = None
    salida = []

    def num():
        nonlocal i
        v = float(tok[i])
        i += 1
        return v

    while i < len(tok):
        if re.fullmatch(r'[A-Za-z]', tok[i]):
            cmd = tok[i]
            i += 1
            if cmd in 'Zz':
                if cur != inicio:
                    salida.append(('L', cur, inicio))
                cur = inicio
                continue
        rel = cmd.islower()
        c = cmd.upper()
        if c == 'M':
            x, y = num(), num()
            cur = inicio = (cur[0] + x, cur[1] + y) if rel else (x, y)
            cmd = 'l' if rel else 'L'   # las coordenadas que siguen a una M son líneas
        elif c == 'L':
            x, y = num(), num()
            nuevo = (cur[0] + x, cur[1] + y) if rel else (x, y)
            salida.append(('L', cur, nuevo))
            cur = nuevo
        elif c == 'H':
            x = num()
            nuevo = (cur[0] + x, cur[1]) if rel else (x, cur[1])
            salida.append(('L', cur, nuevo))
            cur = nuevo
        elif c == 'V':
            y = num()
            nuevo = (cur[0], cur[1] + y) if rel else (cur[0], y)
            salida.append(('L', cur, nuevo))
            cur = nuevo
        elif c in 'CS':
            if c == 'C':
                vals = [num() for _ in range(6)]
                pts = [(vals[k], vals[k + 1]) for k in (0, 2, 4)]
                if rel:
                    pts = [(cur[0] + px, cur[1] + py) for px, py in pts]
                c1, c2, fin = pts
            else:
                vals = [num() for _ in range(4)]
                pts = [(vals[k], vals[k + 1]) for k in (0, 2)]
                if rel:
                    pts = [(cur[0] + px, cur[1] + py) for px, py in pts]
                c2, fin = pts
                c1 = (2 * cur[0] - ultimo_ctrl[0], 2 * cur[1] - ultimo_ctrl[1]) if ultimo_ctrl else cur
            salida.append(('C', cur, c1, c2, fin))
            ultimo_ctrl, cur = c2, fin
            continue
        else:
            sys.exit(f'✗ comando de trazado no soportado: «{cmd}» en «{d}». Añádelo antes de extraer.')
        ultimo_ctrl = None
    return salida


# ── CONVERSIÓN DE UN GLIFO ────────────────────────────────────────────────────────────────────
HEREDABLES = ('fill', 'stroke', 'stroke-width')


def f(el, k, defecto=0.0):
    return float(el.attrib.get(k, defecto))


def convertir(svg: str) -> list[str]:
    raiz = ET.fromstring(svg)
    mascaras = {m.attrib['id']: m for m in raiz.iter('mask')}
    salida: list[str] = []

    def estilo(el, padre):
        e = dict(padre)
        for k in HEREDABLES:
            if k in el.attrib:
                e[k] = el.attrib[k]
        return e

    def pinta(e) -> tuple[bool, bool, float]:
        rellena = e.get('fill', 'black') != 'none'
        traza = e.get('stroke', 'none') != 'none'
        return rellena, traza, float(e.get('stroke-width', '1')) / 2

    def forma(el, e) -> list[str]:
        rellena, traza, r = pinta(e)
        tag = el.tag
        piezas: list[str] = []
        if tag == 'circle':
            cx, cy, rad = f(el, 'cx'), f(el, 'cy'), f(el, 'r')
            if traza and not rellena:
                piezas.append(f'<path fill-rule="evenodd" d="{d_circulo(cx, cy, rad + r)}{d_circulo(cx, cy, max(0, rad - r))}"/>')
            elif rellena:
                piezas.append(circulo(cx, cy, rad + (r if traza else 0)))
        elif tag == 'rect':
            x, y, w, h, rx = f(el, 'x'), f(el, 'y'), f(el, 'width'), f(el, 'height'), f(el, 'rx')
            if traza and not rellena:
                piezas.append(f'<path fill-rule="evenodd" d="{d_rrect(x - r, y - r, w + 2 * r, h + 2 * r, rx + r)}'
                              f'{d_rrect(x + r, y + r, w - 2 * r, h - 2 * r, rx - r)}"/>')
            elif rellena:
                g = r if traza else 0
                piezas.append(f'<path d="{d_rrect(x - g, y - g, w + 2 * g, h + 2 * g, rx + g)}"/>')
        elif tag == 'path':
            d = el.attrib['d']
            if rellena:
                piezas.append(f'<path d="{d}"/>')
            if traza:
                for t in tramos(d):
                    piezas += segmento(t[1], t[2], r) if t[0] == 'L' else curva(t[1], t[2], t[3], t[4], r)
        else:
            sys.exit(f'✗ elemento no soportado dentro del glifo: <{tag}>.')
        return piezas

    def recorre(el, e):
        for hijo in el:
            if hijo.tag in ('defs', 'mask', 'title'):
                continue
            he = estilo(hijo, e)
            if hijo.tag == 'g':
                if 'mask' in hijo.attrib:
                    mid = re.search(r'#([^)]+)', hijo.attrib['mask']).group(1)
                    salida.append(enmascarado(hijo, he, mascaras[mid]))
                else:
                    recorre(hijo, he)   # ⚠️ el `transform` de la rejilla de muestra se ignora a propósito
                continue
            salida.extend(forma(hijo, he))

    def enmascarado(g, e, mascara) -> str:
        """Los huecos de la máscara (lo que pinta en negro) pasan a subtrazos de UN `path` evenodd."""
        ds = []
        for hijo in g.iter():
            if hijo is g:
                continue
            if hijo.tag == 'path':
                ds.append(hijo.attrib['d'])
            elif hijo.tag in ('circle', 'rect'):
                sys.exit('✗ forma no soportada bajo máscara; solo trazados.')
        for hueco in mascara:
            if hueco.attrib.get('fill') not in ('#000', 'black', '#000000'):
                continue
            if hueco.tag == 'circle':
                ds.append(d_circulo(f(hueco, 'cx'), f(hueco, 'cy'), f(hueco, 'r')))
            elif hueco.tag == 'rect':
                ds.append(d_rrect(f(hueco, 'x'), f(hueco, 'y'), f(hueco, 'width'), f(hueco, 'height'), f(hueco, 'rx')))
        return f'<path fill-rule="evenodd" d="{"".join(ds)}"/>'

    recorre(raiz, estilo(raiz, {}))
    return salida


# ── LECTURA DEL ARTBOARD ──────────────────────────────────────────────────────────────────────
def glifo_zona(art: str, nombre: str) -> str:
    sec = art[art.find('07 / ZONAS'):art.find('08 / RESERVAS')]
    i = sec.find('>' + nombre + '<')
    if i < 0:
        sys.exit(f'✗ el artboard no trae la ficha de zona «{nombre}».')
    a = sec.rfind('<svg', 0, i)
    return sec[a:sec.find('</svg>', a) + 6]


def glifo_ui(art: str, codigo: str) -> str:
    i = art.find('>' + codigo + '<')
    if i < 0:
        sys.exit(f'✗ el artboard no trae el icono «{codigo}».')
    a = art.rfind('<svg', 0, i)
    return art[a:art.find('</svg>', a) + 6]


def main() -> None:
    if not ART.is_file():
        sys.exit(f'✗ No está la copia del canvas en {ART}. Bájala antes (`CARRIL-SPA.md` §2); no se inventa nada.')
    if not KIT.is_file():
        sys.exit(f'✗ No hay kit instalado en {KIT}: este guion AÑADE símbolos a uno existente.')

    art = ART.read_text(encoding='utf-8')
    fuentes = {k: (glifo_zona(art, nombre), '0 0 64 64', titulo) for k, nombre, titulo in ZONAS}
    fuentes |= {k: (glifo_ui(art, codigo), '0 0 24 24', titulo) for k, codigo, titulo in DEL_PARQUE}

    kit = KIT.read_text(encoding='utf-8')
    comparacion = []
    for clave in EMITIR:
        svg, vb, titulo = fuentes[clave]
        piezas = ''.join(convertir(svg))
        for a in PROHIBIDOS:
            if re.search(r'(?<![-\w])' + re.escape(a) + r'\s*=', piezas):
                sys.exit(f'✗ {clave}: la conversión dejó `{a}`. El kit se rechazaría entero.')
        simbolo = f'<symbol id="{clave}" viewBox="{vb}"><title>{titulo}</title>{piezas}</symbol>'
        kit = re.sub(r'\s*<symbol id="' + re.escape(clave) + r'"[^>]*>.*?</symbol>', '', kit, flags=re.S)
        kit = kit.replace('</svg>', f'  {simbolo}\n</svg>', 1)
        # ⚠️ El original de la comparación pierde también el `<g transform>` de su rejilla de muestra:
        # la conversión lo ignora, así que compararla con él mediría el transform, no la conversión.
        original = svg
        envoltorio = re.search(r'<g transform="translate\(12 12\)[^"]*">', original)
        if envoltorio:
            resto = original[envoltorio.end():]
            cierre = resto.rfind('</g>')
            original = original[:envoltorio.start()] + resto[:cierre] + resto[cierre + 4:]
        original = re.sub(r'\swidth="\d+"\s+height="\d+"', '', original, count=1)
        comparacion.append(
            f'<div class="par" data-clave="{clave}"><svg class="o" width="192" height="192"{original[4:]}'
            f'<svg class="c" width="192" height="192" viewBox="{vb}" fill="#101418">{piezas}</svg></div>')

    KIT.write_text(kit, encoding='utf-8')
    CMP.parent.mkdir(parents=True, exist_ok=True)
    CMP.write_text('<!doctype html><meta charset="utf-8"><body style="margin:0;color:#101418">'
                   + ''.join(comparacion) + '</body>', encoding='utf-8')
    print(f'✓ {len(EMITIR)} símbolo(s) escritos en {KIT.relative_to(RAIZ)} · comparación en {CMP.relative_to(RAIZ)}')


if __name__ == '__main__':
    main()
