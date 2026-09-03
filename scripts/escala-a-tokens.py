#!/usr/bin/env python3
"""
TANDA F de la auditoría de diseño (`docs/specs/auditoria-diseno.md` §4·M2, `DECISIONES #437`):
la sustitución MECÁNICA de literales por el token del MISMO píxel — cero reflujo por construcción.

    font-size: 13px          →  font-size: var(--fs-13)
    padding: 12px 20px       →  padding: var(--sp-12) var(--sp-20)
    margin: 0 0 16px         →  margin: 0 0 var(--sp-16)

Solo se toca lo que tiene token EXACTO: la escala `--fs-*` (9 · 10 · 11 · 12 · 13 · 14 · 15 · 16 · 17
· 18 · 20 · 22) y la `--sp-*` (1 · 2 · 3 · 4 · 6 · 7 · 8 · 10 · 11 · 12 · 13 · 14 · 15 · 16 · 18 · 20
· 28), las dos declaradas en `landing.css` sobre `--fs-unit`/`--sp-unit` = 1px. Lo que NO se toca, a
propósito: valores con `calc()`/`clamp()`/`var()`, negativos, decimales, porcentajes y `em`, los bloques
`:root` (son la escala misma) y los `@keyframes`, y NADA dentro de un comentario.

Uso:  python3 scripts/escala-a-tokens.py            (informe, sin escribir)
      python3 scripts/escala-a-tokens.py --aplicar  (reescribe las dos hojas)
"""
import re
import sys

SHEETS = ['public/css/landing.css', 'public/css/site.css']
FS = {9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 20, 22}
SP = {1, 2, 3, 4, 6, 7, 8, 10, 11, 12, 13, 14, 15, 16, 18, 20, 28}
SPACE_PROPS = {
    'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left', 'padding-inline', 'padding-block',
    'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left', 'margin-inline', 'margin-block',
    'gap', 'row-gap', 'column-gap',
}


def spans(css: str, pattern: str) -> list[tuple[int, int]]:
    return [(m.start(), m.end()) for m in re.finditer(pattern, css, flags=re.S)]


def inside(pos: int, ranges: list[tuple[int, int]]) -> bool:
    return any(a <= pos < b for a, b in ranges)


def token_px(val: str, scale: set[int], prefix: str):
    """`13px` con token exacto → `var(--fs-13)`; si no, None."""
    m = re.fullmatch(r'(\d+)px', val)
    if not m or int(m.group(1)) not in scale:
        return None
    return f'var(--{prefix}-{m.group(1)})'


def procesar(css: str):
    comentarios = spans(css, r'/\*.*?\*/')
    keyframes = spans(css, r'@keyframes[^{]*\{(?:[^{}]*\{[^{}]*\})*\s*\}')
    # los bloques `:root { … }` son la escala misma
    roots = spans(css, r'(?:^|\n):root\s*\{[^{}]*\}')
    fuera = comentarios + keyframes + roots

    # ⚠️ Se BUSCA sobre una copia con los comentarios blanqueados (misma longitud, mismas posiciones) y
    # se ESCRIBE sobre el original: un `font-size: 13px /* … */;` llevaba el comentario dentro del
    # valor capturado y se saltaba en silencio — la guarda (`ScaleTokensAreUsedTest`) sí lo veía.
    ciego = re.sub(r'/\*.*?\*/', lambda m: re.sub(r'[^\n]', ' ', m.group(0)), css, flags=re.S)

    out = []
    last = 0
    cambios = {'font-size': 0, 'espacio': 0}
    saltados = 0
    # cada declaración `prop: valor;` (o hasta `}`), sin entrar en paréntesis
    for m in re.finditer(r'(?<![-\w])(font-size|' + '|'.join(sorted(SPACE_PROPS, key=len, reverse=True)) + r')\s*:\s*([^;{}]*?)(?=\s*[;}])', ciego):
        prop, val = m.group(1), m.group(2)
        start, end = m.start(2), m.end(2)
        if inside(m.start(), fuera):
            continue
        v = val.strip()
        if not v or any(k in v for k in ('var(', 'calc(', 'clamp(', 'min(', 'max(', 'em', '%', 'vw', 'vh', 'auto', '!important')):
            continue
        if prop == 'font-size':
            nuevo = token_px(v, FS, 'fs')
            if nuevo is None:
                continue
            cambios['font-size'] += 1
        else:
            partes = v.split()
            if not all(re.fullmatch(r'\d+px|0', p) for p in partes) or all(p == '0' for p in partes):
                continue
            tokens = [p if p == '0' else token_px(p, SP, 'sp') for p in partes]
            if any(t is None for t in tokens):
                saltados += 1
                continue
            nuevo = ' '.join(tokens)
            cambios['espacio'] += 1
        out.append(css[last:start])
        out.append(nuevo)
        last = end
    out.append(css[last:])
    return ''.join(out), cambios, saltados


def main() -> int:
    aplicar = '--aplicar' in sys.argv
    for sheet in SHEETS:
        css = open(sheet, encoding='utf-8').read()
        nuevo, cambios, saltados = procesar(css)
        print(f"{sheet}: font-size → token {cambios['font-size']} · espacio → token {cambios['espacio']} · "
              f"espacio con algún escalón SIN token (se deja) {saltados}")
        if aplicar and nuevo != css:
            open(sheet, 'w', encoding='utf-8').write(nuevo)
            print('   escrito')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
