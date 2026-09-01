#!/usr/bin/env python3
"""Genera el sprite `client-kit.svg` del 2.º cliente desde el artboard «Elementos Fachada» (`#309`).

⚠️⚠️ **ESTE GUION EXISTE PORQUE NI EL KIT NI SU FUENTE VIAJAN EN EL REPO.** `public/img/client-kit.svg`
está gitignorado (es el paquete de la instalación) y `mockup_playjumppark/` también (`DECISIONES #1`:
este repo es el producto sin marca de cliente). Sin él, la reconstrucción de los símbolos sería
«acuérdate de cómo se hizo», que es como `#302` dejó un kit que nadie podía rehacer.
Mismo papel que `scripts/logo-sombra.php` y `scripts/logo-letra-a.php`.

⚠️ **Es IDEMPOTENTE y parte del kit de `#302`**: conserva sus cuatro símbolos tal cual y añade los
tres de `#309`. Si el kit instalado ya trae siete, hay que restaurar el de cuatro antes de correrlo
(el guion lo comprueba y avisa en vez de duplicar).

Uso:  python3 scripts/kit-fachada.py && php artisan kit:build --check

⚠️ La geometría se COPIA con un guion, no se transcribe (la regla de `#257`): transcribir a mano
un `<path>` de 1.000 caracteres es un error silencioso esperando a que alguien mire el dibujo.

⚠️⚠️ El kit NO puede traer atributos de presentación (`IllustrationKit::FORBIDDEN_ATTRS`): en el
artboard el color vive en el `style` del `<svg>` contenedor, así que se extrae SOLO el contenido
del `<g>`/`<path>` interior, que ya viene limpio. El guion lo COMPRUEBA antes de escribir.
"""
import re
import sys

ART = "mockup_playjumppark/Elementos Fachada.dc.html"
DESTINO = "public/img/client-kit.svg"
ACTUAL = "public/img/client-kit.svg"

PROHIBIDOS = ['fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin',
              'stroke-dasharray', 'stroke-dashoffset', 'style', 'color', 'opacity',
              'fill-opacity', 'stroke-opacity']

art = open(ART, encoding='utf-8', errors='replace').read()


def limpio(fragmento: str, donde: str) -> str:
    """Revienta si el fragmento trae un atributo de presentación. No sanea: avisa."""
    for a in PROHIBIDOS:
        if re.search(r'(?<![-\w])' + re.escape(a) + r'\s*=', fragmento):
            sys.exit(f"✗ {donde}: trae `{a}`. El kit se rechazaría entero.")
    return fragmento


# ── 1 · Las manchas (viewBox -12 -12 224 224). Cada una es un <g> con path + circles ──────────
# ⚠️ Se deduplica por la FORMA (el `path`), no por el cuerpo entero: la mancha nº 4 sale dos veces
# en el artboard —una con 3 motas dentro de una composición y otra con las 7 que llevan todas—, y
# comparar cuerpos daba SIETE manchas donde hay seis. Se conserva el cuerpo con más motas.
por_forma: dict[str, str] = {}
orden: list[str] = []
for m in re.finditer(r'<svg[^>]*viewBox="-12 -12 224 224"[^>]*>\s*(<g>.*?</g>)\s*</svg>', art, re.S):
    cuerpo = m.group(1)
    forma = re.search(r'\sd="([^"]+)"', cuerpo)
    if forma is None:
        continue
    clave = forma.group(1)
    if clave not in por_forma:
        por_forma[clave] = cuerpo
        orden.append(clave)
    elif cuerpo.count('<circle') > por_forma[clave].count('<circle'):
        por_forma[clave] = cuerpo

manchas = [por_forma[k] for k in orden]

if len(manchas) != 6:
    sys.exit(f"✗ Esperaba 6 manchas distintas y he encontrado {len(manchas)}.")

# ── 2 · Las tres poses del FRISO FAMILIAR (`G3`), con la caja que el artboard les da ──────────
# Cada figura: (viewBox, ancho, alto, ¿espejada?, desplazamiento horizontal previo)
ini = art.index('Friso familiar')
bloque = art[ini:art.find('<section', ini)]
poses = re.findall(r'<svg[^>]*viewBox="([^"]+)"[^>]*>\s*<path[^>]*\sd="([^"]+)"', bloque)
if len(poses) < 3:
    sys.exit(f"✗ El bloque del friso familiar trae {len(poses)} poses; esperaba 3 o más.")

# Las cajas SON las del artboard (leídas de su marcado, no inventadas).
CAJAS = [(96, 104, False, 0), (51, 88, False, -8), (89, 76, True, 0)]

piezas, x = [], 0.0
alto = max(c[1] for c in CAJAS)
for (vb, d), (bw, bh, espejo, solape) in zip(poses[:3], CAJAS):
    vx, vy, vw, vh = (float(n) for n in vb.split())
    # `preserveAspectRatio="xMidYMid meet"`: escala = el menor de los dos, y centrado.
    s = min(bw / vw, bh / vh)
    ox = (bw - vw * s) / 2 - vx * s
    oy = (bh - vh * s) / 2 - vy * s
    x += solape
    # Pies en la misma línea → se alinean por ABAJO dentro del alto común.
    y = alto - bh
    if espejo:
        tr = f"translate({x + bw:.2f} {y + oy:.2f}) scale({-s:.4f} {s:.4f})"
    else:
        tr = f"translate({x + ox:.2f} {y + oy:.2f}) scale({s:.4f})"
    piezas.append(f'<g transform="{tr}"><path d="{d}"></path></g>')
    x += bw

friso = ''.join(piezas)
ancho = round(x, 1)

# ── 3 · Los cuatro símbolos que ya viajan se CONSERVAN tal cual ───────────────────────────────
actual = open(ACTUAL, encoding='utf-8').read()
previos = re.findall(r'(<symbol\b.*?</symbol>)', actual, re.S)
if len(previos) != 4:
    sys.exit(f"✗ El kit instalado trae {len(previos)} símbolos; esperaba los 4 de `#302`.")

nuevos = [
    ('slot-tarifas', f'0 0 {ancho} {alto}', 'Varias personas · friso familiar', friso),
    ('slot-normas-registro', '-12 -12 224 224', 'Mancha · registro obligatorio', manchas[0]),
    ('slot-normas-calcetines', '-12 -12 224 224', 'Mancha · calcetines obligatorios', manchas[2]),
]

salida = ['<svg xmlns="http://www.w3.org/2000/svg">']
salida += ['  ' + p for p in previos]
for clave, vb, titulo, cuerpo in nuevos:
    limpio(cuerpo, clave)
    salida.append(f'  <symbol id="{clave}" viewBox="{vb}"><title>{titulo}</title>{cuerpo}</symbol>')
salida.append('</svg>')

open(DESTINO, 'w', encoding='utf-8').write('\n'.join(salida) + '\n')
print(f"✓ kit escrito: {len(previos) + len(nuevos)} símbolos")
print(f"  slot-tarifas → viewBox 0 0 {ancho} {alto} ({len(friso)} B, 3 figuras)")
for c, vb, _, cu in nuevos[1:]:
    print(f"  {c} → {vb} ({len(cu)} B)")
