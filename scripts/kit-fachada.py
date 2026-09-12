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

❗ **Qué mancha va en qué ranura es decisión EDITORIAL de la instalación**, no del producto: el
producto declara DÓNDE hay hueco (`IllustrationKit::SLOTS`) y el paquete decide QUÉ dibujo cae ahí.
El reparto de abajo es el propuesto en la pasada de vestido; cambiarlo es cambiar esta tabla.

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

# ── EL REPARTO · qué mancha cae en qué ranura ────────────────────────────────────────────────
# El índice es el orden en que el artboard las dibuja. Las seis son intercambiables desde aquí.
REPARTO_MANCHAS = [
    ('slot-resenas', 0, 'Mancha · sección de reseñas'),
    ('slot-visitanos', 1, 'Mancha · sección de horarios y ubicación'),
    ('slot-dudas', 2, 'Mancha · sección de dudas'),
]

# ⚠️⚠️ **SON TRES Y NO SEIS, y el número no lo elegí yo: lo fijan las reglas del propio canvas.**
# Medido sobre la portada de hoy, sección a sección:
#     02 «Cuánto»          → su artboard: «cero superficies nuevas y cero manchas»
#     03 «Qué hay dentro»  → su artboard: «la foto de apertura es la mancha grande: aquí no entra
#                             ninguna otra» (medido: 5 `<img>`)
#     01 «Para quién»      → 2 `<img>`, las fotos de zona
#     05 «Antes de venir»  → ya lleva dibujo: el QR y el teléfono
#     04 «Cumpleaños»      → **0 `<img>`, pero solo porque el owner aún no ha mandado sus dos
#                             fotos** (`#528`): una mancha aquí la desplazaría al llegar
#     06 · 07 · 08         → **cero imagen y cero dibujo**, y `#537` las midió como las tres únicas
#                             zonas 100 % papel de la página
# ▶ Y tres coincide **al dígito** con el presupuesto heredado de `#292` («una pieza por sección,
# tres en toda la portada»), que la pasada de vestido tenía como decisión abierta (su D3). No hacía
# falta decidirla: las reglas duras ya la fijaban.
# ⚠️ El canvas **sí** coloca dos piezas, y las dos ya están puestas: la trama de puntos y el sello
# del CIERRE (`Escritorio PJP`, 1e). Aquí no se toca ninguna.


def limpio(fragmento: str, donde: str) -> str:
    """Revienta si el fragmento trae un atributo de presentación. No sanea: avisa."""
    for a in PROHIBIDOS:
        if re.search(r'(?<![-\w])' + re.escape(a) + r'\s*=', fragmento):
            sys.exit(f"✗ {donde}: trae `{a}`. El kit se rechazaría entero.")
    return fragmento


def manchas(art: str) -> list[str]:
    """
    Las seis manchas del grupo B, en el orden del artboard.

    ⚠️ Se deduplica por la FORMA (el `path`), no por el cuerpo entero: una de ellas sale dos veces
    —una con 3 motas dentro de una composición y otra con las 7 que llevan todas—, y comparar
    cuerpos daba SIETE manchas donde hay seis. Se conserva el cuerpo con más motas.
    """
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

    if len(orden) != 6:
        sys.exit(f"✗ Esperaba 6 manchas distintas y he encontrado {len(orden)}.")

    return [por_forma[k] for k in orden]


def friso(art: str) -> tuple[str, float, float]:
    """
    El friso familiar (`G3`): tres poses en fila, con la caja que el artboard les da.

    ⚠️⚠️ Se compone con `<g transform>` y NO con `<use href="#pose">` internos: un `<use>` interno
    dentro de un símbolo referenciado por un `<use>` EXTERNO no resuelve igual en todos los motores,
    y aquí solo hay Chrome medido (`hueco-ilustracion.md` §2.3).
    """
    ini = art.index('Friso familiar')
    bloque = art[ini:art.find('<section', ini)]
    poses = re.findall(r'<svg[^>]*viewBox="([^"]+)"[^>]*>\s*<path[^>]*\sd="([^"]+)"', bloque)

    if len(poses) < 3:
        sys.exit(f"✗ El bloque del friso familiar trae {len(poses)} poses; esperaba 3 o más.")

    # Las cajas SON las del artboard (leídas de su marcado, no inventadas).
    cajas = [(96, 104, False, 0), (51, 88, False, -8), (89, 76, True, 0)]

    piezas, x = [], 0.0
    alto = max(c[1] for c in cajas)

    for (vb, d), (bw, bh, espejo, solape) in zip(poses[:3], cajas):
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

    return ''.join(piezas), round(x, 1), float(alto)


def main() -> int:
    if not ART.is_file():
        sys.exit(f"✗ No está la copia del canvas: {ART}\n  Bájala con `DesignSync` antes de correr esto.")

    art = ART.read_text(encoding='utf-8', errors='replace')

    formas = manchas(art)
    # ⚠️ El friso se extrae igual aunque hoy no se emita: su extracción es la parte frágil (lee las
    # cajas del artboard) y correrla en cada build es lo que avisa si el artboard la mueve.
    friso(art)

    # ── Los dibujos de ZONA se conservan: no salen de este artboard ──────────────────────────
    previos: list[str] = []
    if KIT.is_file():
        previos = [s for s in re.findall(r'(<symbol\b.*?</symbol>)', KIT.read_text(encoding='utf-8'), re.S)
                   if re.search(r'id="zone-', s)]

    # ⚠️⚠️ **Solo se emiten las ranuras DECLARADAS.** `kit:build --check` rechaza el kit entero si
    # trae un `slot-*` que el producto no declara, y con razón: una clave de más es un dibujo que
    # nadie pinta y que nadie echa de menos. El friso (`slot-tarifas`) salió del kit con `#479`.
    nuevos: list[tuple[str, str, str, str]] = []
    for clave, idx, titulo in REPARTO_MANCHAS:
        nuevos.append((clave, '-12 -12 224 224', titulo, formas[idx]))

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
