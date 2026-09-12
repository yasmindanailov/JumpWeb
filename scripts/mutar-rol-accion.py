#!/usr/bin/env python3
"""
ARNÉS DE MUTACIÓN de la grieta 01 — ¿muerden las guardas del rol de acción del cajón?

En este repo una guarda no vale hasta que se la ve morder con el fallo REAL que la motivó. Y en esta
tanda hacía falta de verdad: el predicado de `SidebarActionRoleTest` **nació demasiado estrecho** y
acusó a dos botones perfectamente correctos (el de borrar la cuenta y el de Google), y el censo de
`--action` **nació demasiado ancho** (`var(--action\\b` casa con `var(--action-hover)`). Las dos cosas
las dijo el test al correrlo, no una relectura.

Cada mutación reproduce una forma distinta de deshacer la tanda o de relajar su red:

   1. el CTA del pie vuelve a la marca                      → el censo de marca
   2. «Pagar» pierde el relleno de acción                   → el reparto del relleno de acción
   3. la tira de cuenta recupera el naranja                 → lo mismo, por la otra puerta
   4. la barra del carrito recupera el naranja              → lo mismo
   5. el pie deduce su clase en vez de leer el dato         → «the footer class comes from the sells flag»
   6. el paso 4 también dice que vende                      → el caso de JS del mapa del naranja
   7. un botón que no vende pierde su variante              → «only the buttons that charge…»
   8. el canal secundario del aviso vuelve a `.btn` pelado  → lo mismo (el defecto preexistente)
   9. un enlace del cajón vuelve al color de marca          → «the rescued text roles read their role»
  10. el día elegido vuelve a la marca                      → el censo de marca
  11. la píldora del contador vuelve a la marca             → el censo de marca
  12. `.btn--ink` rellena con marca                         → «the ink variant fills with ink»
  13. `.btn--zone` vuelve a declararse                      → «the absorbed family is not in the sheets»
  14. el vocabulario de estados pierde `.is-selected`       → su caso de control
  15. el escáner se queda sin corpus                        → «the scanner sees the drawer»

⚠️ **Exige el árbol COMMITEADO**: el arnés restaura con `git checkout`, y `#448` dejó el árbol mutado
dos veces por confiar en un `trap` que no corre con SIGKILL.
⚠️⚠️ **Y DESPUÉS DE CORRERLO, `npm run build:ssr` ANTES DE LA SUITE.** Este arnés muta ficheros `.vue`
del cajón, y `SidebarDomContractTest` **renderiza el bundle SSR, no las fuentes**: tras la pasada la
suite salió con los **35** casos en rojo de siempre —el número exacto que `CARRIL-SPA.md` §4 tiene
fichado— con el árbol restaurado y limpio. Reconstruir lo arregló. *Que el arnés deje el ÁRBOL como
estaba no quiere decir que deje el BUNDLE como estaba.*
⚠️ **Cada mutación COMPRUEBA que el fichero cambió** antes de correr el caso: en `#500` cinco
mutaciones «sobrevivieron» sin haberse aplicado nunca.
⚠️ El veredicto es el CÓDIGO DE SALIDA, nunca un `grep` de «passed».

    python3 scripts/mutar-rol-accion.py
"""
import subprocess
import sys
from pathlib import Path

SITE = Path('public/css/site.css')
LANDING = Path('public/css/landing.css')
FOOT_VUE = Path('resources/js/sidebar/steps/Foot.vue')
FOOT_JS = Path('resources/js/sidebar/foot.js')
LOGIN = Path('resources/js/sidebar/steps/LoginForm.vue')
PAUSED = Path('resources/js/sidebar/steps/PausedNotice.vue')
GUARDA = Path('tests/Feature/Architecture/SidebarActionRoleTest.php')
GUARDA_ZONA = Path('tests/Feature/Theme/InteractionColourIsNotAZoneTest.php')

PHP = 'SidebarActionRoleTest|SingleButtonFamilyTest|InteractionColourIsNotAZoneTest|ActionFillTest'

# (rótulo, motor, fichero, antes, después)
MUTACIONES = [
    ('el CTA del pie vuelve a la marca', 'php', SITE,
     'border-radius: var(--r-btn); background: var(--fg); color: var(--bg); font-family: var(--font-body); font-weight: var(--fw-bold); font-size: var(--fs-button); transition: transform var(--dur-estado) var(--ease-entra), background',
     'border-radius: var(--r-btn); background: var(--zone-1); color: var(--on-brand); font-family: var(--font-body); font-weight: var(--fw-bold); font-size: var(--fs-button); transition: transform var(--dur-estado) var(--ease-entra), background'),

    ('«Pagar» pierde el relleno de acción', 'php', SITE,
     '.bk-cta--sells { background: var(--action); color: var(--on-action); }',
     '.bk-cta--sells { background: var(--fg); color: var(--bg); }'),

    ('la tira de cuenta recupera el naranja', 'php', SITE,
     '.acct__btn--primary { background: var(--fg); color: var(--bg); }',
     '.acct__btn--primary { background: var(--action); color: var(--on-action); }'),

    ('la barra del carrito recupera el naranja', 'php', SITE,
     'width: 100%; border: 0; cursor: pointer; background: var(--fg); color: var(--bg); border-radius: var(--r-btn);',
     'width: 100%; border: 0; cursor: pointer; background: var(--action); color: var(--on-action); border-radius: var(--r-btn);'),

    ('el pie deduce su clase en vez de leer el dato', 'php', FOOT_VUE,
     ':class="footer.sells ? \'bk-cta--sells\' : \'\'"',
     ':class="footer.action === \'confirmReservation\' ? \'bk-cta--sells\' : \'\'"'),

    # ⚠️ Ésta la caza el caso de JS y NO la guarda PHP: el censo estático no puede saber en qué paso se
    # pasa el argumento. Por eso el arnés corre los dos motores.
    ('el paso 4 también dice que vende', 'js', FOOT_JS,
     "cartFooter(state, 'checkout', t(messages, 'go_to_pay'), 'arrow', 'popover')",
     "cartFooter(state, 'checkout', t(messages, 'go_to_pay'), 'arrow', 'popover', true)"),

    ('un botón que no vende pierde su variante', 'php', LOGIN,
     'class="btn btn--ink auth__submit"',
     'class="btn auth__submit"'),

    ('el canal secundario del aviso vuelve a `.btn` pelado', 'php', PAUSED,
     ":class=\"cta.primary ? 'btn--ink' : 'btn--ghost'\"",
     ":class=\"cta.primary ? 'btn--ink' : ''\""),

    ('un enlace del cajón vuelve al color de marca', 'php', SITE,
     "font-weight: var(--fw-bold); color: var(--interactive); transition: gap var(--dur-sale) var(--ease-sale); }",
     "font-weight: var(--fw-bold); color: var(--zone-1); transition: gap var(--dur-sale) var(--ease-sale); }"),

    ('el día elegido vuelve a la marca', 'php', SITE,
     '    background: var(--fg); border-color: var(--fg);\n    box-shadow: 0 0 0 2px var(--fg) inset;\n    color: var(--bg);',
     '    background: var(--zone-1); border-color: var(--zone-1);\n    box-shadow: 0 0 0 2px var(--fg) inset;\n    color: var(--on-brand);'),

    ('la píldora del contador vuelve a la marca', 'php', SITE,
     'border-radius: var(--r-md); background: var(--ok); color: var(--on-ok); font-family: var(--font-display);',
     'border-radius: var(--r-md); background: var(--zone-1); color: var(--on-brand); font-family: var(--font-display);'),

    ('`.btn--ink` rellena con marca', 'php', LANDING,
     '.btn--ink { background: var(--fg); color: var(--bg); }',
     '.btn--ink { background: var(--zone-1); color: var(--on-brand); }'),

    ('`.btn--zone` vuelve a declararse', 'php', LANDING,
     '.btn--ink:disabled:hover,',
     '.btn--zone { background: var(--zone-1); }\n.btn--ink:disabled:hover,'),

    ('se cae la pestaña acotada al panel', 'php', SITE,
     '.sidecart__panel .zone-tab.active { background: var(--fg); color: var(--bg); border-color: var(--fg); }',
     '/* .sidecart__panel .zone-tab.active — retirada por la mutación */'),

    # ⚠️ El CONTRARIO: alguien «termina el trabajo» tocando la regla base y cambia el color de
    # tarifas y de `/servicios`, que son del otro carril.
    ('la regla BASE de la pestaña se «arregla» también', 'php', LANDING,
     '.zone-tab.active { background: var(--zone-1); color: var(--on-brand); }',
     '.zone-tab.active { background: var(--fg); color: var(--bg); }'),

    ('el vocabulario de estados pierde `.is-selected`', 'php', GUARDA_ZONA,
     "\\.is-on\\b|\\.is-selected\\b|\\.is-current\\b",
     "\\.is-on\\b|\\.is-current\\b"),

    # ⚠️ Se vacía la lista ENTERA: con veintitrés prefijos de los veinticuatro el censo sigue pasando de
    # cien reglas, así que quitar uno no probaría nada (la lección del arnés de `#550`).
    ('el escáner se queda sin corpus', 'php', GUARDA,
     "        'acc-tile', 'acct', 'account__', 'addons__', 'auth__', 'bk-', 'cal-more', 'cal__', 'cart',\n"
     "        'cartbar', 'catalog', 'daystrip', 'dep-pick', 'entry__', 'guardnote', 'orders__', 'paydue',\n"
     "        'prod-ico', 'purchase', 'qr-pass', 'qtybox', 'timestrip', 'whoblock', 'wiz__',",
     "        'zzz-no-existe',"),
]


def corre(cmd: list[str]) -> int:
    return subprocess.run(cmd, capture_output=True, text=True).returncode


def en_verde(motor: str) -> bool:
    if motor == 'js':
        return corre(['docker', 'compose', 'exec', '-u', 'sail', '-T', 'laravel.test',
                      'npm', 'run', 'test:js']) == 0

    return corre(['docker', 'compose', 'exec', '-u', 'sail', '-T', 'laravel.test',
                  'php', 'artisan', 'test', '--filter', PHP]) == 0


def main() -> int:
    if subprocess.run(['git', 'status', '--porcelain'], capture_output=True, text=True).stdout.strip():
        print('✗ El árbol tiene cambios sin commitear. Commitea antes de mutar: el arnés restaura con git.')
        return 1

    for motor in ('php', 'js'):
        print(f'CONTROL · ¿el motor {motor} pasa con el árbol sano? ', end='', flush=True)
        if not en_verde(motor):
            print('NO → el arnés no puede decir nada. Arregla la guarda primero.')
            return 1
        print('sí')

    muerden = 0

    for rotulo, motor, fichero, antes, despues in MUTACIONES:
        texto = fichero.read_text(encoding='utf-8')

        if antes not in texto:
            print(f'✗ {rotulo}: no se encontró el texto a mutar — la mutación NO se aplicó (revísala)')
            continue

        fichero.write_text(texto.replace(antes, despues, 1), encoding='utf-8')

        if fichero.read_text(encoding='utf-8') == texto:
            print(f'✗ {rotulo}: el fichero no cambió')
            continue

        muerde = not en_verde(motor)
        subprocess.run(['git', 'checkout', '--', str(fichero)], check=True)

        print(f'{"✓" if muerde else "✗"} [{motor}] {rotulo}{"" if muerde else "  ← SOBREVIVE: la guarda no lo ve"}')
        muerden += int(muerde)

    print(f'\n{muerden}/{len(MUTACIONES)} mutaciones muerden')
    return 0 if muerden == len(MUTACIONES) else 1


if __name__ == '__main__':
    sys.exit(main())
