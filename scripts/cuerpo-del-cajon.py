#!/usr/bin/env python3
"""
LA GRIETA 00 — el cuerpo del cajón pasa de 13 px al suelo del sistema.

`[DECIDIDO owner, 2026-09-11]`: **16 en las 25 pantallas**. La grieta la reportó el propio canvas
auditando nuestro código (`Auditoria Sistema SPA PJP`, grieta 00) y su `doc/spa.md` la registra como
cerrada en la parada 05: *«sube el texto un 23 %, así que el reflujo se revisa pieza por pieza; y con
ella se caen los tamaños de 13, 11 y 9»*.

── POR QUÉ UN GUION Y NO CIEN EDICIONES ─────────────────────────────────────────────────────────
Son **115 reglas** y el precedente es del propio repo: `#437` convirtió 662 literales con
`scripts/escala-a-tokens.py`, informe en seco y `--aplicar`. Lo que hace revisable un cambio así no
es teclearlo a mano: es que **la tabla de abajo diga, regla por regla, a qué nivel va y por qué** —y
que el guion se niegue a tocar nada que no esté en ella—.

── LA REGLA QUE DECIDE EL NIVEL ─────────────────────────────────────────────────────────────────
Las tallas NO se copian de los artboards: sus ficheros mezclan la pantalla dibujada con el aparato de
anotación (rótulos y notas a 10-12 px), así que raspar números de ahí da una cifra creíble y falsa.
Salen de `tokens-pjp.js` v1.10 y de `doc/reglas.md`:

  · `--fs-body`   (16 móvil / 17 escritorio) — lo que se LEE: nombres, importes, valores, listas,
    campos y sus rótulos, estados vacíos, títulos de bloque. El suelo del sistema.
  · `--fs-body-s` (15) — el APOYO: pistas, notas, avisos legales, metadatos, pies de línea. Y las
    pestañas, que el sistema fija en 15 (`componente.pestana`).
  · `--fs-label`  (12) — la ETIQUETA: mono, versalitas o con tracking; chapas, contadores y estados.
    Es el nivel más pequeño del sistema y por debajo no hay nada (`doc/reglas.md`: «etiquetas mono
    nunca bajo 10 px», y la Etiqueta son 12).
  · `--fs-button` (16) — el rótulo de un botón de verdad. ⚠️ Estrenarlo obliga a sacarlo de
    `SidebarTokenBudgetTest::SIN_ESTRENAR`, que es el trinquete que existe para eso. Aquí se estrena
    **solo dentro del cajón**: el peso 800 y el borde del botón del sistema son otra tanda.

── LO QUE ESTE GUION NO TOCA ────────────────────────────────────────────────────────────────────
  · **El post-form y el justificante** (`.gf-*`, `.guestform__*`): son otra área del canvas
    (`doc/formulario.md`) y tienen su propia lista de encargos.
  · **La web** (`.events__*`, `.party-form__*`, `.addons-mini__*`, `.socks-note__*`): carril del otro
    agente.
  · **Las 18 reglas de clases COMPARTIDAS** (`.form__label`, `.check`, `.zone-tab`, `.cal__*`…): se
    suben ACOTADAS al cajón en un bloque aparte, porque la misma clase la emiten el formulario de
    contacto, el de recuperar contraseña, el post-form y el calendario de «Crear pedido» del panel
    (`[DECIDIDO owner]`). Eso lo escribe `--acotadas`, no esta tabla.
  · **Lo que no es texto**: `.acct__alert-ico` fija el cuerpo de un glifo dentro de un círculo de
    18 px; subirlo no lee mejor, desborda su caja.

Uso:
    python3 scripts/cuerpo-del-cajon.py            # informe en seco
    python3 scripts/cuerpo-del-cajon.py --aplicar  # escribe
"""
import re
import sys
from pathlib import Path

HOJA = Path('public/css/site.css')

# ── LA TABLA ─────────────────────────────────────────────────────────────────────────────────────
# selector exacto (tal y como aparece en la hoja) → nivel. Cada bloque lleva su porqué.
NIVEL = {}

# Lo que se LEE: el cuerpo del cajón. Es la grieta 00 en sentido estricto.
for sel in [
    '.purchase__empty',            # estado vacío: es la única frase de la pantalla
    '.purchase__chip',             # la hora, dentro de su ficha
    '.purchase__chip-t',
    '.purchase__split',            # «Señal X · resto en el parque»
    '.purchase__confirm',          # «carrito listo», que es un aviso que se lee entero
    '.purchase__reginfo-text',
    '.bk-context',                 # la línea de contexto de la banda: día y hora elegidos
    '.bk-foot__pop-row',           # las dos filas del desglose de la señal
    '.bk-paybreakdown__row',
    '.cart__when', '.cart__price', '.cart__lines li', '.cart__event li', '.cart__addons li',
    '.catalog__name', '.catalog__price', '.catalog-acc__title',
    '.catalog-search__input',      # campo: el sistema lo fija en 16
    '.entry__qty',                 # el número de la cantidad, que además se teclea
    '.qtybox__label',              # «Invitados» / «Cantidad»: rótulo de un control
    '.addons__name',
    '.orders__line', '.orders__code', '.orders__gate', '.orders__mov',
    # El nombre de una línea que NO es complemento. El `:not()` cita un modificador muerto
    # (`.orders__line--addon`, que no emite nadie) y por eso la regla no se retira: quitarlo
    # cambiaría su especificidad, y esta regla sí pinta.
    '.orders__line:not(.orders__line--addon) .orders__line-name',
    '.orders__event li', '.orders__guests-what', '.orders__guests-item',
    '.orders__product-form-link',
    '.purchase__code',
    '.acc-tile__name',             # el nombre de cada zona en el índice de la cuenta
    '.acct__qr', '.acct__alert',
    '.account__consents li',
    '.auth__sent', '.auth__google',
    '.whoblock__title', '.guardnote__label',
    '.cal__legend-item', '.cal-more',
    # ⚠️ Estas dos familias se le escaparon al primer censo y las cazó la SONDA, no la lectura: el
    # barrido las descartó por su nombre —`qr-` cayó en el filtro de «esto es de la web»— y en
    # pantalla salieron igual, con «Mi QR» y «Menores a cargo» llenos de texto pequeño. Las emite
    # solo el cajón (`CardZone.vue` y el selector de menores), verificado clase a clase.
    '.qr-pass__code',              # el carné, que se dicta en voz alta: cuanto más grande, mejor
    '.qr-pass__empty',             # estado vacío
]:
    NIVEL[sel] = '--fs-body'

# El APOYO: lo que acompaña a una decisión sin ser la decisión.
for sel in [
    '.purchase__note', '.purchase__cta-tertiary',
    '.bk-back', '.bk-foot__l', '.bk-foot__note',
    '.paydue__hint', '.paydue__legal',
    '.qtybox__avail',              # «quedan N plazas»
    '.catalog__feat', '.catalog__deposit',
    '.price__from',
    '.addons__intro', '.addons__price', '.addons__perguest', '.addons__requires', '.addons__group-label',
    '.cart__addon-incl', '.cart__deposit',
    '.orders__meta', '.orders__line-unit', '.orders__ledger-note', '.orders__mov-date',
    '.orders__gate-caption', '.orders__guests-hint', '.orders__guests-ack', '.orders__retry-hint',
    '.acct__sub', '.account__card-sub',
    '.auth__switch', '.auth__or',
    '.whoblock__hint', '.guardnote__help', '.guardnote__text',
    '.qr-pass__notice',            # el aviso bajo el carné
    '.dep-pick__age',              # la edad del menor, al lado de su nombre
    '.dep-pick__why',              # por qué una fila no se puede marcar (`#242`: el motivo se conserva)
]:
    NIVEL[sel] = '--fs-body-s'

# La ETIQUETA: mono, versalitas o tracking; chapas, contadores y estados.
for sel in [
    '.bk-step-count', '.bk-seg__label',
    '.cartbar__label',
    '.catalog__badge', '.catalog-acc__count',
    '.daystrip__month', '.daystrip__wd', '.daystrip__price',
    '.cal__day-price',
    '.addons__badge',
    '.orders__status', '.orders__ledger-title', '.orders__line-badge', '.orders__guests-link',
    '.orders__guests-count',
    '.acct__count',
    '.purchase__chip-full',        # «Casi llena» / «Agotado»: contexto de la ficha, no la ficha
    '.dep-pick__ok',               # «1 entrada asignada»: estado, en segundo plano (`#242`)
]:
    NIVEL[sel] = '--fs-label'

# BOTONES de verdad: su rótulo es el nivel Botón (16). El peso y el borde son otra tanda.
for sel in [
    '.bk-cta', '.cartbar__go', '.purchase__add-more', '.orders__gate-toggle', '.auth__link',
]:
    NIVEL[sel] = '--fs-button'

# ── LAS ACOTADAS ─────────────────────────────────────────────────────────────────────────────────
# Clases COMPARTIDAS con otras superficies: se suben SOLO dentro del panel del cajón.
# `.sidecart__panel` delante basta para ganar (mismo fichero, más abajo, y una clase más de
# especificidad); `landing.css` se carga ANTES que `site.css`, así que `.zone-tab` también cae.
ACOTADAS = [
    ('.form__field > .form__label', '--fs-body', 'el rótulo de un campo'),
    ('.form__field textarea', '--fs-body', 'campo: el sistema lo fija en 16'),
    ('.form__field select', '--fs-body', 'campo'),
    ('.form__error', '--fs-body-s', 'el aviso de un campo'),
    ('.form__hint', '--fs-body-s', 'la pista de un campo'),
    ('.check', '--fs-body', 'el texto de una casilla se lee entero'),
    ('.switch', '--fs-body', 'lo mismo, en interruptor'),
    ('.auth__sub', '--fs-body-s', 'la entradilla de las pantallas de auth'),
    ('.auth__errors', '--fs-body-s', 'el aviso del limitador'),
    ('.eventfields__label', '--fs-body', 'rótulo de un campo del pack'),
    ('.addons__moreinfo', '--fs-body-s', 'enlace de «más info»'),
    ('.addons__features li', '--fs-body-s', 'las ventajas desplegadas'),
    ('.cal__month', '--fs-body', 'el mes del calendario'),
    ('.cal__day', '--fs-body', 'el número del día'),
    ('.cal__wd', '--fs-label', 'la inicial del día de la semana'),
    ('.acct__btn', '--fs-button', 'el botón del bloque de cuenta'),
    ('.zone-tab', '--fs-body-s', 'pestaña: el sistema la fija en 15 (componente.pestana)'),
    ('.purchase__note', '--fs-body-s', 'la entradilla del paso 5'),
    # ⚠️ La familia `.btn` entra AQUÍ y no en la tabla: es de la web entera (`#321`, una sola familia)
    # y su talla la mueve la tanda del botón del sistema. Pero dentro del cajón se quedaba a 14 al
    # lado de un «Ir a pagar» de 16 —medido en navegador: `.btn--zone`, `.btn--ghost` y el de borrar
    # la cuenta—, y eso es justo la mezcla que la grieta 00 existe para quitar.
    ('.btn', '--fs-button', 'el rótulo de un botón; la familia de la web no se toca'),
    # ⚠️⚠️ **Las cuatro últimas las cazó el chequeo de COMPLETITUD, no el censo**: son clases que el
    # cajón emite y que se escapaban a la vez de sus bloques y de sus prefijos. `.eventfields input |
    # select | textarea` son los CAMPOS del pack —el sistema los fija en 16— y `.pagination__info` es
    # el «1 de 3» de las listas de la cuenta. Las comparten el post-form, el justificante y el
    # paginador de la web, así que van acotadas como las demás, no en su regla base.
    ('.eventfields input', '--fs-body', 'campo del pack: el sistema lo fija en 16'),
    ('.eventfields select', '--fs-body', 'campo del pack'),
    ('.eventfields textarea', '--fs-body', 'campo del pack'),
    ('.pagination__info', '--fs-body-s', 'el «1 de 3» de las listas de la cuenta'),
]

# ── LAS MUERTAS ──────────────────────────────────────────────────────────────────────────────────
# Sus clases no las emite NADIE: ni Vue, ni Blade, ni PHP, ni el manifiesto congelado del contrato de
# árbol (verificado clase a clase antes de escribir esto). `[DECIDIDO owner]`: se retiran. No se viste
# lo que no se pinta — y si se quedaran, habría que decidirles un tamaño y excluirlas de la guarda.
# ⚠️ `.orders__line--addon` NO está aquí: vive dentro del `:not()` de una regla VIVA
# (`.orders__line:not(.orders__line--addon) .orders__line-name`), así que retirarla cambiaría la
# especificidad de una regla que sí pinta. Queda anotada como modificador muerto.
MUERTAS = [
    '.events__tab', '.events__tab:hover', '.events__tab.is-active', '.events__pack-terms',
    '.account__subhead', '.account__muted',
    '.entry__name', '.entry__price', '.entry__avail',
    '.orders__line-included', '.orders__product-deposit', '.orders__refund',
    '.orders__gate-line', '.orders__gate-line strong',
    '.manage__intro', '.manage__code-label', '.manage__note',
]

TALLA = {'--fs-body': '16/17', '--fs-body-s': '15', '--fs-label': '12', '--fs-button': '16'}


def bloques(texto):
    """Cada regla de la hoja: (selector, inicio, fin, cuerpo). No entra en `@media` anidados."""
    for m in re.finditer(r'([^{}]+)\{([^{}]*)\}', texto):
        sel = m.group(1).strip().split('\n')[-1].strip()
        if sel and not sel.startswith('@'):
            yield sel, m.start(), m.end(), m.group(2)


def main():
    aplicar = '--aplicar' in sys.argv
    texto = HOJA.read_text(encoding='utf-8')
    cambios, retiradas, sin_tocar = [], [], []

    # 1. Las muertas, de abajo arriba para no mover los índices de las de arriba.
    for sel, ini, fin, cuerpo in sorted(bloques(texto), key=lambda b: -b[1]):
        if sel in MUERTAS:
            retiradas.append((sel, texto[:ini].count('\n') + 1))
            if aplicar:
                # ⚠️⚠️ **Se consume el salto de línea de DELANTE, no el de detrás**, y la primera
                # versión hacía lo contrario: al llevarse el posterior, lo que venía después —un
                # comentario, o la regla siguiente— quedaba PEGADO al final de la línea anterior
                # (`.entry__info { … }.entry__stepper { … }`). El CSS parsea igual, así que **ninguna
                # guarda lo ve**; lo que se rompe es la lectura de la hoja. Lo cazó auditar el diff,
                # que es lo que este guion declara como su auditoría.
                antes_del_corte = texto[:ini]
                texto = antes_del_corte.rstrip('\n') + '\n' + texto[fin:].lstrip('\n')

    # 2. El nivel de cada regla viva de la tabla.
    salida = []
    resto = texto
    for sel, ini, fin, cuerpo in bloques(texto):
        m = re.search(r'font-size:\s*(var\(--fs-[\d.]+\)|[\d.]+px)', cuerpo)
        if not m:
            continue
        if sel not in NIVEL:
            if re.match(r'\.(bk-|purchase|catalog|cal[_-]|daystrip|entry__|cart|qtybox|addons__|acct|acc-tile|account__|orders__|auth__|whoblock|guardnote|paydue|cartbar|price__from)', sel):
                sin_tocar.append((sel, texto[:ini].count('\n') + 1, m.group(1)))
            continue
        nuevo = f'font-size: var({NIVEL[sel]})'
        cambios.append((sel, texto[:ini].count('\n') + 1, m.group(1), NIVEL[sel]))
        if aplicar:
            salida.append((ini + cuerpo.find(m.group(0)) + len(sel) + 1, m.group(0), nuevo))

    if aplicar:
        # se reemplaza por REGLA, buscando dentro de su propio cuerpo: así una misma declaración
        # repetida en dos reglas no se confunde.
        nuevo_texto = ''
        pos = 0
        for sel, ini, fin, cuerpo in bloques(texto):
            if sel not in NIVEL or 'font-size' not in cuerpo:
                continue
            arreglado = re.sub(r'font-size:\s*(?:var\(--fs-[\d.]+\)|[\d.]+px)',
                               f'font-size: var({NIVEL[sel]})', cuerpo, count=1)
            nuevo_texto += texto[pos:ini] + texto[ini:fin].replace(cuerpo, arreglado, 1)
            pos = fin
        nuevo_texto += texto[pos:]
        texto = nuevo_texto

        # 3. El bloque acotado de las compartidas, al FINAL de la hoja (gana por orden y por peso).
        # ⚠️ **Idempotente**: si el bloque ya está, se REEMPLAZA. Sin esto, reaplicar el guion lo
        # añadía una segunda vez —y dos copias de la misma regla no fallan, solo dejan la hoja
        # diciendo dos veces lo mismo y la siguiente edición tocando la copia equivocada—.
        marca = 'EL CUERPO DEL CAJÓN · las clases COMPARTIDAS'
        if marca in texto:
            texto = texto[:texto.rindex('/* ' + '=' * 68, 0, texto.index(marca))].rstrip() + '\n'

        acotadas = ['\n/* ' + '=' * 68,
                    '   EL CUERPO DEL CAJÓN · las clases COMPARTIDAS, acotadas al panel (grieta 00).',
                    '',
                    '   Estas clases las emiten también el formulario de contacto, el de recuperar',
                    '   contraseña, los chips de complementos de la landing, el formulario post-reserva,',
                    '   el justificante y el calendario de «Crear pedido» del panel. Subirlas en su sitio',
                    '   movería cinco superficies que nadie ha revisado, así que `[DECIDIDO owner]` se',
                    '   suben SOLO aquí dentro. El día que cada superficie se vista, este bloque encoge.',
                    '   ' + '=' * 68 + ' */']
        for sel, nivel, porque in ACOTADAS:
            acotadas.append(f'.sidecart__panel {sel} {{ font-size: var({nivel}); }}  /* {porque} */')
        texto += '\n'.join(acotadas) + '\n'

        HOJA.write_text(texto, encoding='utf-8')

    print(f'REGLAS MOVIDAS: {len(cambios)}')
    for nivel in ('--fs-body', '--fs-body-s', '--fs-label', '--fs-button'):
        filas = [c for c in cambios if c[3] == nivel]
        print(f'\n  {nivel} ({TALLA[nivel]}) · {len(filas)}')
        for sel, linea, antes, _ in filas:
            print(f'    {antes:16} → {sel[:50]:50} :{linea}')
    print(f'\nREGLAS MUERTAS RETIRADAS: {len(retiradas)}')
    for sel, linea in sorted(retiradas, key=lambda r: r[1]):
        print(f'    :{linea:<6} {sel}')
    print(f'\nACOTADAS AL PANEL (clases compartidas): {len(ACOTADAS)}')
    if sin_tocar:
        print(f'\n⚠️ DEL VOCABULARIO DEL CAJÓN Y FUERA DE LA TABLA ({len(sin_tocar)}) — mirar una a una:')
        for sel, linea, valor in sin_tocar:
            print(f'    {valor:16} {sel[:54]:54} :{linea}')
    print('\n' + ('ESCRITO.' if aplicar else 'EN SECO: no se ha tocado nada. Repite con --aplicar.'))


if __name__ == '__main__':
    main()
