#!/usr/bin/env python3
"""
LA HOJA DEL CAJÓN — `public/css/cajon.css`, GENERADA desde `public/css/site.css`.

⚠️⚠️ **Se GENERA, no se parte, y esa es la decisión que ordena la T4** (`specs/cajon-empaquetable.md` §4.3).
Partir la hoja —mover las reglas del cajón fuera de `site.css`— parecía lo natural hasta medirlo: de los 101
bloques BEM que emite el cajón, **45 los pinta también la landing** (`btn` en 19 ficheros Blade, `form`,
`check`, `pagination`, `sr-only`, `tabset`, y las tripas de once iconos SVG compartidos). Mover cualquiera de
ésos deja la landing sin estilo; duplicarlos a mano crea dos copias que divergen al primer retoque.

Generando, el reparto es otro: **`site.css` no se toca** —la landing del producto queda intacta por
construcción, y su huella de maquetación es idéntica sin necesidad de fe— y `cajon.css` es un EXTRACTO de esas
mismas reglas, para una página que no quiere las 1.352 reglas de la landing. Una regla compartida sale en las
dos hojas, pero se escribe UNA vez.

── QUÉ ENTRA ────────────────────────────────────────────────────────────────────────────────────
  1. Las reglas cuyo selector nombra una clase que el cajón puede emitir (los `.vue` + la carcasa).
  2. Los tokens y la base que el cajón HEREDA: `:root`, `html`, `body` y `*`.
  3. Lo que esté dentro de un `@media`/`@supports` conserva su contexto.
Y nada más: lo que no case no viaja, y quien decide si falta algo no es este guion sino el NAVEGADOR
(`scripts/huella-maquetacion.mjs --cajon`, que compara el cajón con una hoja y con la otra).

── CÓMO SE CORRE ────────────────────────────────────────────────────────────────────────────────
    python3 scripts/hoja-del-cajon.py              # informe en seco: qué entraría y qué se queda fuera
    python3 scripts/hoja-del-cajon.py --aplicar    # escribe public/css/cajon.css

⚠️ El informe en seco es la mitad del método de `#437`: un cambio mecánico que nadie puede revisar antes de
aplicarlo no es revisable después.
"""
from __future__ import annotations

import hashlib
import re
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
CSS = RAIZ / 'public/css'
DESTINO = CSS / 'cajon.css'
VUE = RAIZ / 'resources/js/sidebar'

# ⚠️⚠️ **De dónde sale la hoja, y por qué son TRES y no una** (medido el 2026-09-18):
#   · `site.css`    — el grueso de las reglas del cajón. Es la fuente obvia y la única que se creía necesaria.
#   · `landing.css` — los TOKENS del sistema viven ahí, no en `site.css`: la hoja generada leía 121 custom
#     properties y definía 79; de las 42 que faltaban, 65 referencias resolvían en `landing.css` (`--fg`,
#     `--bg`, `--fs-*`, `--font-*`…). Y además 31 reglas suyas son del cajón (ver abajo).
#   · `spinner.css` — el velo de carga del cajón es el spinner de marca, y vive en su propia hoja. Entra para
#     que la promesa de §4.1 se cumpla: la landing escribe DOS líneas, la hoja y el cargador. Una sola hoja.
#
# ⚠️⚠️ **Van en el ORDEN en que las carga el producto** (`layout.blade.php`: landing → spinner → site → client),
# y no es cosmético: `.btn` tiene 24 reglas en `landing.css` y otras tantas en `site.css`, así que quién gana lo
# decide el orden. Generando primero `site.css` —como se hizo al principio— el paquete invertía la cascada.
#
# ⚠️ **Y `landing.css` no es «solo tokens», que fue la primera suposición y era falsa**: 31 de sus reglas
# nombran un bloque del cajón y `tabset` —las pestañas de Entrar / Crear cuenta— vive SOLO ahí. Con la hoja
# tratándola como escala del sistema, esas dos pestañas salían sin vestir: 32 nodos distintos en el juez.
FUENTES = ('landing.css', 'spinner.css', 'site.css')

# La CARCASA la pinta Blade (o el paquete, en una página ajena), así que sus clases no salen de ningún `.vue`.
# `no-scroll` es la única que el cajón pone FUERA de sí mismo: el cerrojo de scroll la escribe en `<html>` y
# `<body>` del anfitrión, y eso está en el contrato (§4.2), no es una fuga.
CARCASA = {
    'sidecart', 'acct', 'purchase-loading', 'no-scroll',
    'jj-spinner', 'jj-spinner-with-label', 'jj-spinner-label',
}

# ⚠️⚠️ **Los tokens y la base entran SOLO si el selector es exactamente esa base**, y la diferencia no es
# cosmética: con `^\s*(:root|html|body|\*)\b` —lo que había el 2026-09-18— la hoja del paquete se llevaba 39
# reglas, y **34 eran de la landing del anfitrión**: `body[data-has-hero] .nav__left`, `.hero__pair-slot`, las
# animaciones de la marca, el formulario de invitados (`html.js .gf-fiche__body`), `body.maint`, `.offw-launch`…
# y, la peor, `html, body { margin:0; background: var(--bg); color: var(--fg); font-family: … }`. **Un paquete
# que se monta en la página de otro no puede repintarle el `<body>`**; eso no es un cajón, es una intrusión.
BASE = re.compile(r'^(:root|html|body|html\s*,\s*body|\*)$')

# ⚠️ Los DOS huecos del cajón se visten por ID, no por clase: `#sidecart-spa` lleva el `display:flex` que
# reparte el alto del motor. Mirar solo clases lo dejaba fuera y el cuerpo del cajón salía sin repartir (el
# juez lo vio: 711 px de alto contra 430). Van declarados, que son dos.
IDS = re.compile(r'#sidecart-(spa|account)\b')

# ⚠️⚠️ **Dónde se publican los tokens del paquete, y las TRES pasadas que costó** (`specs/cajon-empaquetable.md`
# §4.3, `DECISIONES #635`). Hacen falta a la vez dos cosas que tiran en sentidos opuestos: que la INSTALACIÓN
# tematice el cajón (white-label) y que el ANFITRIÓN no se lo tematice sin querer (empaquetable). Medido el
# 2026-09-18, en este orden:
#   1. `.sidecart` a secas → el valor del producto le gana a `client.css`, que tematiza en `:root`, porque está
#      más CERCA del nodo. El cajón salía con Space Grotesk dentro de la casa del cliente: 1.638 nodos.
#   2. `:where(:root)` (especificidad 0) → `client.css` manda otra vez… y el anfitrión también: su `:root`
#      declara `--bg`, `--fg`, `--line` —nombres genéricos, colisión segura— y el cajón salía con el azul
#      marino de la página ajena y el texto ilegible. Se vio en la captura, no en una medición.
#   3. `:where(.sidecart)` → la PROXIMIDAD le gana al `:root` del anfitrión (la herencia mira el ancestro más
#      cercano que declare, no la especificidad), y la especificidad 0 hace que dentro del cajón le gane
#      cualquiera que declare sobre `.sidecart`. Que es lo que a partir de aquí hace el tema de la instalación:
#      `:root, .sidecart` en su bloque de tokens (`docs/INSTALACION-CLIENTE.md` §4).
# ▶ Jerarquía resultante, que es la que pide el producto: **tema de la instalación → paquete → anfitrión**.
RAIZ_PAQUETE = ':where(.sidecart)'

# ⚠️ **El suelo del paquete se escribe con `:where()` y no es un adorno.** Lo que en el producto pintaba
# `button { font: inherit }` tiene especificidad 0,0,1; al meterlo bajo `.sidecart ` pasaría a 0,1,1 y **le
# ganaría a `.acct__btn`**, que es 0,1,0: el reset acabaría pisando a las reglas que viste. Con `:where` la
# especificidad se queda en la de origen y la cascada del producto se reproduce tal cual.
RAIZ_CAJON = ':where(.sidecart)'

# La BASE del paquete: lo que el cajón heredaba del documento y ahora se da a sí mismo. Se escribe a mano —son
# dos reglas— porque las del producto vienen mezcladas con el reset del anfitrión (`margin`, `padding`,
# `background`), que no es nuestro. Espejo exacto de lo que el navegador calculaba dentro del cajón:
#   · `html, body { color; font-family; -webkit-font-smoothing; -moz-osx-font-smoothing }` → se HEREDA, así que
#     ponerlo en la raíz del cajón da los mismos valores calculados dentro.
#   · `* { box-sizing: border-box }` → sin pseudoelementos, como en el producto: añadir `::before`/`::after`
#     cambiaría el dibujo respecto a la landing y el juez lo cantaría.
BASE_DEL_PAQUETE = """/* La BASE del paquete: lo que el cajón heredaba del `<body>` del producto y ahora se da
 * a sí mismo, para no tocar el documento del anfitrión. Ver la cabecera del generador. */
:where(.sidecart), :where(.sidecart) * {
    box-sizing: border-box;
}
:where(.sidecart) {
    color: var(--fg);
    font-family: var(--font-body);
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}"""

# Un paso de `@keyframes` no es un selector de documento por mucho que se escriba sin punto.
PASO_DE_ANIMACION = re.compile(r'^(from|to|[\d.]+%)$')


def bloques_del_cajon() -> set[str]:
    """Los bloques BEM que el cajón puede emitir: los de sus `.vue` más los de la carcasa."""
    encontrados = set(CARCASA)

    for fichero in VUE.rglob('*.vue'):
        for lista in re.findall(r'class="([^"]+)"', fichero.read_text(encoding='utf-8')):
            for clase in lista.split():
                if re.fullmatch(r'[a-z][a-z0-9-]*(__[a-z0-9-]+)?(--[a-z0-9-]+)?', clase):
                    encontrados.add(re.split(r'__|--', clase)[0])

    return encontrados


def reglas(css: str) -> list[tuple[str, str, str]]:
    """(contexto @, selector, cuerpo) de cada regla, resolviendo un nivel de `@media`/`@supports`/`@layer`."""
    salida: list[tuple[str, str, str]] = []
    pila: list[str] = []
    buf = ''
    i, n = 0, len(css)

    while i < n:
        if css.startswith('/*', i):
            j = css.find('*/', i + 2)
            i = (j + 2) if j > 0 else n
            continue

        c = css[i]

        if c == '{':
            cabecera = ' '.join(buf.split())
            buf = ''

            if cabecera.startswith('@') and not cabecera.startswith('@font-face'):
                pila.append(cabecera)
                i += 1
                continue

            profundidad, j = 1, i + 1
            while j < n and profundidad:
                if css.startswith('/*', j):
                    k = css.find('*/', j + 2)
                    j = (k + 2) if k > 0 else n
                    continue
                if css[j] == '{':
                    profundidad += 1
                elif css[j] == '}':
                    profundidad -= 1
                j += 1

            salida.append((' '.join(pila), cabecera, css[i + 1:j - 1].strip()))
            i = j
            continue

        if c == '}':
            if pila:
                pila.pop()
            buf = ''
            i += 1
            continue

        buf += c
        i += 1

    return salida


def entra(selector: str, patron: re.Pattern) -> bool:
    return bool(BASE.match(selector) or IDS.search(selector) or patron.search(selector))


def base_de_documento(contexto: str, selector: str) -> bool:
    """¿Es una regla de ELEMENTO —sin clase, sin id— de las que el cajón usaba por venir del documento?

    Son cinco en `landing.css` (`a`, `button`, `img`, `::selection` y el foco de los campos) y las cinco las
    necesita el cajón: sin el `button { font: inherit }` el aspa de cerrar sale en **Arial y 12 px más ancha**,
    y el `<header>` crece 2 px que desplazan el panel entero. Lo dijo el juez, no el criterio de nadie.
    """
    if contexto.startswith('@keyframes') or re.search(r'[.#\[]', selector) or BASE.match(selector):
        return False

    partes = [p.strip() for p in selector.split(',')]

    return all(p and not PASO_DE_ANIMACION.match(p) and re.fullmatch(r'[a-zA-Z][\w-]*(::?[\w-]+(\([^()]*\))?)*|::[\w-]+', p) for p in partes)


def bajo_el_cajon(selector: str) -> str:
    return ', '.join(f'{RAIZ_CAJON} {p.strip()}' for p in selector.split(','))


def solo_tokens(cuerpo: str) -> str:
    """De una regla base solo viajan sus CUSTOM PROPERTIES; lo demás (el reset) es del anfitrión.

    ⚠️ **Los comentarios se conservan y hay que trocear POR ELLOS.** Al filtrar partiendo el cuerpo por `;` a
    secas, todo token precedido de un comentario —y `site.css` explica casi uno por uno— empezaba por `/*` y se
    caía: de 201 definiciones quedaban 124, y el informe cantó **458 lecturas desnudas**. El porqué de un token
    vale tanto como su valor, así que viajan los dos.
    """
    salida: list[str] = []

    for trozo in re.split(r'(/\*.*?\*/)', cuerpo, flags=re.S):
        if trozo.startswith('/*'):
            salida.append(trozo)
        else:
            salida += [f'{d.strip()};' for d in trozo.split(';') if d.strip().startswith('--')]

    return '\n'.join(salida)


def sin_comentarios(css: str) -> str:
    """El cuerpo de una regla viaja crudo, comentarios incluidos: nombrar un token no es leerlo."""
    return re.sub(r'/\*.*?\*/', '', css, flags=re.S)


def desnudas(hoja: str) -> list[tuple[str, str]]:
    """Las lecturas que ni definen el token aquí ni traen respaldo: ésas NO pintan en una página ajena.

    ⚠️⚠️ **La lista de nombres huérfanos no es el criterio; el respaldo sí.** Medido el 2026-09-18: de los 11
    nombres que salían «sin definir», tres (`--cta-pair-h`, `--jump-1`, `--jump-2`) solo estaban NOMBRADOS en un
    comentario, y los ocho restantes son ganchos de instalación (`--action-brand`… los pone `client.css`), el
    `--i` que la cascada pone en línea, o tokens dormidos del producto (`--r-s`, `--state-size`, que hoy tampoco
    define nadie en `site.css`). Los once se leen con `var(--x, respaldo)`, así que ninguna regla se queda sin
    pintar. Lo que sí sería un defecto es una lectura desnuda, y eso es una cuenta, no un juicio.
    """
    limpia = sin_comentarios(hoja)
    definidos = set(re.findall(r'^\s*(--[\w-]+)\s*:', limpia, flags=re.M))
    sueltas = []

    for m in re.finditer(r'var\(\s*(--[\w-]+)\s*(.)', limpia):
        if m.group(1) not in definidos and m.group(2) != ',':
            sueltas.append((m.group(1), ' '.join(limpia[max(0, m.start() - 80):m.start()].split())[-60:]))

    return sueltas


def informe_de_tokens(hoja: str) -> str:
    limpia = sin_comentarios(hoja)
    leidos = set(re.findall(r'var\(\s*(--[\w-]+)', limpia))
    definidos = set(re.findall(r'^\s*(--[\w-]+)\s*:', limpia, flags=re.M))
    huerfanos = sorted(leidos - definidos)
    sueltas = desnudas(hoja)

    lineas = [f'\ntokens: lee {len(leidos)} · define {len(definidos)} · de fuera: {len(huerfanos)}']
    if huerfanos:
        lineas.append('    ' + ' '.join(huerfanos))
        lineas.append('    (de fuera = los pone `client.css`, la línea del `style=`, o nadie: todos con respaldo)')
    lineas.append(f'lecturas DESNUDAS (sin definir y sin respaldo → la regla no pinta): {len(sueltas)}')
    for token, contexto in sueltas[:10]:
        lineas.append(f'    ❌ {token}  ←  …{contexto}')

    return '\n'.join(lineas)


def main() -> int:
    aplicar = '--aplicar' in sys.argv
    bloques = bloques_del_cajon()
    # ⚠️⚠️ **La mirada negativa tiene que dejar pasar el `__` Y el `--` de BEM, y rechazar un `-` suelto.**
    # Las dos mitades costaron una pasada del juez cada una el 2026-09-18:
    #   · con `(?![a-zA-Z0-9_-])` el bloque `sidecart` **no casaba con `.sidecart__panel`** y la hoja salía
    #     válida, de 55 kB… y sin vestir nada (1.422 nodos distintos);
    #   · arreglado el `_`, seguía sin casar `.check--opt` y el rótulo de una casilla salía del color
    #     equivocado (22 nodos distintos).
    # Lo que SÍ se rechaza es `-` seguido de nombre, para que el bloque `cal` no se lleve `.cal-more`.
    patron = re.compile(
        r'\.(' + '|'.join(sorted(map(re.escape, bloques), key=len, reverse=True)) + r')(?![a-zA-Z0-9])(?!-[a-zA-Z0-9])'
    )

    dentro: list[tuple[str, str, str]] = []
    fuera: list[tuple[str, str, str]] = []
    resumen: list[str] = []

    for nombre in FUENTES:
        css = (CSS / nombre).read_text(encoding='utf-8')
        todas = reglas(css)
        elegidas: list[tuple[str, str, str]] = []

        for ctx, sel, cuerpo in todas:
            # La base del documento viaja REESCRITA: de `:root`/`body` solo salen los tokens, y a la raíz del
            # paquete; las reglas de elemento, bajo la raíz del cajón. Una regla base sin tokens (el reset del
            # anfitrión: `margin`, `padding`, `background`) desaparece, que no es del cajón.
            if BASE.match(sel):
                if solo_tokens(cuerpo).strip():
                    elegidas.append((ctx, RAIZ_PAQUETE, solo_tokens(cuerpo)))
            elif base_de_documento(ctx, sel):
                elegidas.append((ctx, bajo_el_cajon(sel), cuerpo))
            elif entra(sel, patron):
                elegidas.append((ctx, sel, cuerpo))

        dentro += elegidas
        fuera += [r for r in todas if not entra(r[1], patron)]
        resumen.append(f'  {nombre:<12} {len(css):>7} B · {len(todas):>4} reglas → {len(elegidas):>4}')

    # Se reconstruye agrupando por contexto, para no repetir la cabecera de cada `@media`.
    # ⚠️ **El sello es lo que hace vigilable la hoja.** Sin él, tocar `site.css` y olvidar regenerar no lo nota
    # nadie: la hoja sigue siendo CSS válido, solo que de ayer. `HojaDelCajonTest` recalcula estos hashes.
    sello = ' '.join(
        f'{n}:{hashlib.sha1((CSS / n).read_bytes()).hexdigest()[:12]}' for n in FUENTES
    )
    partes: list[str] = [
        '/* GENERADO por scripts/hoja-del-cajon.py desde public/css/{site,landing,spinner}.css. NO se edita a mano:',
        ' * se toca la regla en su fuente y se vuelve a generar. `HojaDelCajonTest` vigila que no se separen.',
        ' * Qué entra y por qué: la cabecera de ese guion y `specs/cajon-empaquetable.md` §4.3.',
        f' * FUENTES {sello} */',
        BASE_DEL_PAQUETE,
    ]
    # ⚠️⚠️ **Se agrupan TRAMOS CONSECUTIVOS, no todas las reglas de cada `@media`.** Agrupando por contexto —lo
    # que hacía la primera versión— las at-rules se coleccionaban y salían TODAS al final, detrás de las de
    # primer nivel: eso reordena la cascada. Lo cazó el juez con tres nodos a 1280 px, `@media (min-width:1024px)
    # { .tabset { width: 352px } }` de `landing.css` ganándole a `.purchase__authtabs` de `site.css`, que en el
    # producto gana por ir después. **Un extracto que reordena no es el mismo CSS**, y solo se ve al medirlo.
    contexto_abierto = ''

    for contexto, selector, cuerpo in dentro:
        if contexto != contexto_abierto:
            if contexto_abierto:
                partes.append('}')
            if contexto:
                partes.append(f'{contexto} {{')
            contexto_abierto = contexto

        sangria = '    ' if contexto else ''
        cuerpo_sangrado = '\n'.join(f'{sangria}    {l.strip()}' for l in cuerpo.splitlines() if l.strip())
        partes.append(f'{sangria}{selector} {{\n{cuerpo_sangrado}\n{sangria}}}')

    if contexto_abierto:
        partes.append('}')

    hoja = '\n'.join(partes) + '\n'

    bytes_hoja = len(hoja.encode('utf-8'))
    fuentes_bytes = sum(len((CSS / n).read_bytes()) for n in FUENTES)
    print('fuentes:')
    print('\n'.join(resumen))
    print(f'cajon.css  : {bytes_hoja:>7} B · {len(dentro):>4} reglas  ({bytes_hoja * 100 // fuentes_bytes} % de las tres fuentes)')
    print(f'se quedan fuera: {len(fuera)} reglas · bloques del cajón: {len(bloques)}')

    print(informe_de_tokens(hoja))

    if desnudas(hoja):
        print('❌ hay lecturas desnudas: esas reglas NO pintan en una página ajena. No se aplica.')
        return 1

    # Lo que un revisor necesita ver: reglas EXCLUIDAS que aun así nombran algo del cajón en su cuerpo,
    # y reglas incluidas cuyo selector mezcla el cajón con la landing (salen en las dos hojas a propósito).
    mezcladas = [
        s for _, s, _ in dentro
        if patron.search(s) and re.search(r'\.[a-zA-Z][\w-]*', s) and s != RAIZ_PAQUETE
        and any(re.split(r'__|--', c)[0] not in bloques
                for c in re.findall(r'\.([a-zA-Z][\w-]*)', s))
    ]
    print(f'\nselectores que MEZCLAN cajón y no-cajón (viajan en las dos hojas, a propósito): {len(mezcladas)}')
    for s in mezcladas[:10]:
        print(f'    {s[:110]}')

    if not aplicar:
        print('\n(en seco: nada escrito. `--aplicar` para generar)')
        return 0

    DESTINO.write_text(hoja, encoding='utf-8')
    print(f'\n✓ escrito {DESTINO.relative_to(RAIZ)}')

    return 0


if __name__ == '__main__':
    raise SystemExit(main())
