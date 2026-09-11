"""Poda reglas CSS cuyo selector solo cita clases que se han quedado sin consumidor.

⚠️⚠️ **ESTE INSTRUMENTO ESTÁ VERSIONADO A PROPÓSITO**, como `sonda-geometria.mjs` y
`comparar-con-mockup.mjs`: es la lección de `#475` —*un instrumento que no viaja en el repo se
vuelve a escribir, y al reescribirlo se vuelven a pagar sus trampas*—. Lo estrenó `#482` al retirar
el carrusel de atracciones (38 reglas, 229 líneas) y **las cinco secciones que le quedan a la Fase 2
van a retirar CSS igual**.

── CÓMO SE CORRE ────────────────────────────────────────────────────────────────────────────────
  1. Edita `FAMILIAS` / `EXACTAS` con las clases que se han quedado sin consumidor. ⚠️ Compruébalo
     antes **por clase EXACTA y por fichero** (`#309`): un `grep` que SÍ encuentra tampoco demuestra
     que exista — puede estar dentro de un comentario, que es como `.ride-card` «vivía» en
     `visit.blade.php`.
  2. `python3 scripts/podar-css-huerfano.py`            → pasada EN SECO, lista lo que se iría
  3. `python3 scripts/podar-css-huerfano.py --aplicar`  → recorta y verifica el balance de llaves

── ❗❗ LAS CUATRO TRAMPAS QUE ESTE GUION YA PAGÓ ─────────────────────────────────────────────────
 1. **Hay LLAVES dentro de COMENTARIOS.** `landing.css` cita `> :not(.ilu) { position: relative }`
    en prosa, y también nombres de clase. Un analizador que no los enmascara abre reglas donde no
    las hay **y salva de la poda reglas cuyo comentario menciona una clase viva**. Con la máscara
    aparecieron **seis reglas más**. ▶ Se parsea sobre una copia enmascarada (comentarios a
    espacios, misma longitud) y se corta sobre el original con esos índices.
 2. **Una regla puede tener varios selectores y solo uno condenado.** La primera versión iba a
    **borrar el foco de teclado de casillas y radios**, porque `.ride-card:focus-visible` era uno de
    los seis selectores de esa regla. ▶ Una regla se va solo si **todos** sus selectores se van, y
    un selector **sin clases** (`input[type="checkbox"]:focus-visible`) no se condena nunca. Las
    MIXTAS se listan aparte para arreglarlas a mano.
 3. **Recortar por índices exactos se come los saltos de línea**: dejaba `}@media (…) {}` pegado a
    la regla siguiente, con la hoja cuadrando de llaves y **ilegible**. ▶ El corte va por LÍNEAS
    enteras, y **aborta** si en esa línea hay algo vivo delante o detrás.
 4. **`inicio` es el final de la regla ANTERIOR, no el principio del selector.** Sin normalizarlo,
    la guarda de la trampa 3 aborta con razón sobre la regla equivocada.

⚠️ Y la trampa de `#293`, que es la que lo motiva todo: un recorte a ojo se llevó **una llave de
más** y la hoja se leyó a medias **sin fallar**. Por eso al final se verifica el balance, y por eso
la verificación se hace sobre la copia ENMASCARADA: contar llaves con los comentarios dentro daría
un balance falso.

⚠️⚠️ **LO QUE ESTE GUION NO HACE, Y HAY QUE HACER A MANO DESPUÉS**: retirar las entradas muertas de
las listas de excepción de las guardas (`CardSkinTest`, `TagSystemTest`, `TouchTargetTest`,
`ShapeScaleTest`, `MotionBudgetTest`, `InteractionColourIsNotAZoneTest`), los `@keyframes` que se
quedan sin quien los invoque, y los selectores mixtos. **Corre la suite: esas guardas te lo dirán
una a una.**
"""
import re
import sys

RUTA = 'public/css/landing.css'

# Familias enteras (la clase y sus `__elemento` / `--modificador`).
# `#528`: la página vieja de `/cumpleanos` —la banda, la polaroid, el paso a paso y el editor de
# invitaciones—. ⚠️ Cada una es una BASE exacta: `bd-pack-col` no es de la familia `bd-pack`
# (`base()` solo parte por `__` y `--`), así que van todas escritas.
# (La pasada anterior, `#482`, podó el carrusel de atracciones con esta misma lista.)
FAMILIAS = {
    'bd-page', 'bd-main', 'bd-standalone', 'bd-sec1', 'bd-sec2', 'bd-sec3',
    'bd-band', 'bd-tabs', 'bd-tab', 'bd-grid', 'bd-pol', 'bd-pack-col', 'bd-pack',
    'bd-check', 'bd-feat', 'bd-invite-cta', 'bd-proc', 'bd-inv', 'bd-editor',
    'bd-fields', 'bd-field', 'bd-swatches', 'bd-swatch', 'bd-stage', 'bd-shape',
    'bd-card', 'bd-toast', 'bd-day', 'bd-btn',
}
# Clases sueltas cuya FAMILIA sigue viva. ⚠️ Van aparte porque normalizar por familia las dejaría
# fuera de la poda sin decir nada: le pasó a `.zones__juegos` en `#482`, con `.zones` viva.
EXACTAS = {'party__mixed'}


def enmascarar(css: str) -> str:
    """Sustituye el contenido de cada comentario por espacios, conservando los saltos de línea."""
    return re.sub(
        r'/\*.*?\*/',
        lambda m: ''.join('\n' if c == '\n' else ' ' for c in m.group(0)),
        css,
        flags=re.S,
    )


def base(clase: str) -> str:
    """`ride-card__viz` → `ride-card`; `zone-pick__tab` → `zone-pick`."""
    return re.split(r'__|--', clase, maxsplit=1)[0]


def muerta(clase: str) -> bool:
    return clase in EXACTAS or base(clase) in FAMILIAS


def parte_condenada(parte: str) -> bool:
    """Un selector suelto está condenado si alguna clase que EXIGE está muerta.

    ⚠️⚠️ Un selector SIN clases —`input[type="checkbox"]:focus-visible`— NO está condenado, y esto
    es lo que evita el peor falso positivo de este guion: el foco de teclado de casillas y radios
    vive en una regla que además lista `.ride-card:focus-visible`, y la primera versión se la iba a
    llevar entera. (Eso lo resuelve `condenado()` partiendo por comas: aquí se mira UN selector.)

    ⚠️⚠️ **TRAMPA 5 (`#528`): «todas sus clases muertas» era demasiado prudente.** Dentro de UN
    selector —compuesto (`.bd-tab.is-active`) o descendiente (`.bd-pol__ticket .lbl`)— cada clase
    es OBLIGATORIA: basta una sin consumidor para que el selector no pueda casar nunca. La versión
    anterior las salvaba porque `.is-active` o `.lbl` siguen vivas en otra parte, y dejó **dieciocho
    reglas muertas** tras la poda de la página vieja de `/cumpleanos`.
    ▶ Lo que NO es obligatorio es lo que va dentro de `:not()`, `:is()`, `:where()` o `:has()`:
    `:not(.x)` casa precisamente cuando `.x` falta, y `:is(.x, .y)` son alternativas. Se retiran
    antes de contar.
    """
    obligatoria = re.sub(r':(?:not|is|where|has)\([^()]*\)', '', parte)
    clases = re.findall(r'\.(-?[A-Za-z_][A-Za-z0-9_-]*)', obligatoria)

    return any(muerta(c) for c in clases)


def condenado(selector: str) -> bool:
    """La regla se va solo si TODOS sus selectores se van."""
    partes = [p.strip() for p in selector.split(',') if p.strip()]

    return bool(partes) and all(parte_condenada(p) for p in partes)


def mixto(selector: str) -> bool:
    """Regla que pierde ALGUNO de sus selectores pero no todos: se arregla a mano."""
    partes = [p.strip() for p in selector.split(',') if p.strip()]

    return any(parte_condenada(p) for p in partes) and not all(parte_condenada(p) for p in partes)


def reglas(mascara: str, desplazamiento: int = 0):
    """(inicio, fin, selector) de cada regla, entrando en los `@media`."""
    i = 0
    n = len(mascara)
    while i < n:
        j = mascara.find('{', i)
        if j < 0:
            return
        selector = mascara[i:j].strip()
        prof, k = 1, j + 1
        while k < n and prof:
            if mascara[k] == '{':
                prof += 1
            elif mascara[k] == '}':
                prof -= 1
            k += 1
        if selector.startswith('@media') or selector.startswith('@supports'):
            yield from reglas(mascara[j + 1:k - 1], desplazamiento + j + 1)
            i = k
            continue
        yield (i + desplazamiento, k + desplazamiento, selector)
        i = k


def main(aplicar: bool) -> int:
    css = open(RUTA, encoding='utf-8').read()
    mascara = enmascarar(css)
    assert len(mascara) == len(css), 'la máscara cambió de longitud: los índices no valdrían'

    todas = list(reglas(mascara))
    fuera = [r for r in todas if condenado(r[2])]
    mixtas = [r for r in todas if mixto(r[2])]

    print(f'reglas condenadas: {len(fuera)}')
    for inicio, fin, sel in fuera:
        # Se enseña el selector REAL (sin máscara) para poder revisarlo.
        print('  ·', ' '.join(css[inicio:css.find('{', inicio)].split())[-100:])

    print(f'\nreglas MIXTAS (pierden un selector, se arreglan a mano): {len(mixtas)}')
    for _, _, sel in mixtas:
        print('  ⚠', ' '.join(sel.split())[:160])

    if not aplicar:
        return 0

    nuevo = css
    for inicio, fin, _ in sorted(fuera, reverse=True):
        # ⚠️ `inicio` es el final de la regla ANTERIOR, no el principio del selector: entre medias
        # hay espacios y comentarios. En la MÁSCARA los comentarios son espacios, así que saltar
        # espacios aterriza exactamente en el primer carácter del selector.
        j = inicio
        while j < len(mascara) and mascara[j].isspace():
            j += 1

        # Arrastra el comentario inmediatamente anterior, que es la documentación de esa regla.
        cierre = nuevo.rfind('*/', 0, j)
        if cierre != -1 and nuevo[cierre + 2:j].strip() == '':
            apertura = nuevo.rfind('/*', 0, cierre)
            if apertura != -1 and nuevo[:apertura].rstrip(' \t').endswith('\n'):
                j = apertura

        # ⚠️⚠️ **EL CORTE VA POR LÍNEAS ENTERAS, y la primera versión no lo hacía.** Recortar por
        # índices exactos se comía los saltos de línea y dejaba `}@media (…) {}` pegado a la regla
        # siguiente: la hoja seguía cuadrando de llaves y era ilegible. Aquí se corta desde el
        # principio de la línea donde empieza la regla hasta el final de la línea donde acaba.
        # ⚠️ Y si en esa línea hay algo VIVO delante, se aborta: sería el caso de dos reglas en la
        # misma línea, donde cortar la línea entera se llevaría la vecina.
        ini_linea = nuevo.rfind('\n', 0, j) + 1
        if nuevo[ini_linea:j].strip() not in ('', '}'):
            print(f'✕ ABORTADO: hay algo vivo antes de la regla en su línea → {nuevo[ini_linea:j]!r}')
            return 1
        fin_linea = nuevo.find('\n', fin)
        fin_linea = len(nuevo) if fin_linea == -1 else fin_linea + 1
        if nuevo[fin:fin_linea].strip() != '':
            print(f'✕ ABORTADO: hay algo vivo después de la regla en su línea → {nuevo[fin:fin_linea]!r}')
            return 1

        nuevo = nuevo[:ini_linea] + nuevo[fin_linea:]

    # Un `@media` que se queda sin ninguna regla dentro se va con ellas: un bloque vacío no es una
    # decisión, es un resto.
    vacios = re.findall(r'\n@media[^{]*\{\s*\}\n', nuevo)
    nuevo = re.sub(r'\n@media[^{]*\{\s*\}\n', '\n', nuevo)
    if vacios:
        print(f'  · {len(vacios)} bloque(s) `@media` que se quedaron vacíos, retirados')

    m = enmascarar(nuevo)
    if m.count('{') != m.count('}'):
        print(f'✕ BALANCE ROTO: {m.count("{")} abiertas y {m.count("}")} cerradas')
        return 1

    open(RUTA, 'w', encoding='utf-8').write(nuevo)
    print(f'✓ aplicado · balance {m.count("{")}/{m.count("}")} · '
          f'{len(css.splitlines()) - len(nuevo.splitlines())} líneas menos')
    return 0


sys.exit(main('--aplicar' in sys.argv))
