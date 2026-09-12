#!/usr/bin/env python3
"""Arnés de mutación de la PARADA 06 (`#566`): el suelo táctil del cajón, los antetítulos y la fila
que abre un documento legal.

Cada mutación es un defecto REAL que alguien podría cometer. Si una NO muerde, la guarda que debía
cazarla no vale y hay que rehacerla — no bajar la mutación.

    docker compose exec -u sail -T laravel.test python3 scripts/mutar-parada06.py

⚠️⚠️ **Este arnés muta ficheros `.vue`, así que deja el BUNDLE SSR rancio** — la trampa que `#554`
pagó con 35 casos en rojo sobre el árbol limpio. Por eso las guardas que mide leen la FUENTE y no el
bundle, y por eso al terminar se reconstruye igualmente: si alguien corre la suite entera después,
`SidebarDomContractTest` compararía código viejo.

⚠️ **El veredicto es el CÓDIGO DE SALIDA de `artisan test`**, nunca un `grep` de «passed»: un filtro
que no casa con ningún test sale con código 0 y diría que la mutación no muerde.

⚠️ **Y el árbol tiene que estar COMMITEADO antes de correrlo** (la regla de `#181`): una interrupción
deja el fichero mutado, y `git checkout` para deshacerlo se llevaría por delante lo que no esté
guardado.
"""

import subprocess
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent

CSS = 'public/css/site.css'
LOGIN = 'resources/js/sidebar/steps/LoginForm.vue'
REGISTER = 'resources/js/sidebar/steps/RegisterForm.vue'
WAIVER = 'resources/js/sidebar/WaiverDoc.vue'
PRIVACY_ZONE = 'resources/js/sidebar/account/zones/PrivacyZone.vue'
LANG_ES = 'lang/es/account.php'

# Las tres guardas de la tanda, más la que cambió de premisa. Se corren juntas: una mutación puede
# caer en cualquiera, y pedir «cuál» de antemano es cementar a qué fichero pertenece cada propiedad.
GUARDAS = 'SidebarTouchTargetTest|SidebarAuthScreensTest|PrivacyNoticeIsVisibleTest|SidebarTokenBudgetTest'

# (nombre, fichero, buscar, sustituir)
MUTACIONES = [
    # ── El suelo táctil del armazón ──────────────────────────────────────────────────────────
    ('el aspa de cerrar pierde su área táctil y vuelve a los 15×26 medidos',
     CSS,
     '.cart__remove,\n.sidecart__close,\n.bk-foot__info-btn { position: relative; }',
     '.cart__remove,\n.bk-foot__info-btn { position: relative; }'),

    ('el aspa conserva el `position` pero pierde el pseudo que la agranda',
     CSS,
     '.cart__remove::after,\n.sidecart__close::after,\n.bk-foot__info-btn::after {',
     '.cart__remove::after,\n.bk-foot__info-btn::after {'),

    ('el área del «Volver» vuelve al literal que cumplía por casualidad aritmética',
     CSS,
     '    width: max(100%, var(--tap-min)); height: max(100%, var(--tap-min));\n}\n\n/* ⚠️ **El «Volver» del ÁREA lleva su propio aire abajo.**',
     '    inset: -14px calc(var(--sp-8) * -1);\n}\n\n/* ⚠️ **El «Volver» del ÁREA lleva su propio aire abajo.**'),

    ('el área táctil ENCOGE los controles que ya cumplen (el defecto de `#264`)',
     CSS,
     '    width: max(100%, var(--tap-min)); height: max(100%, var(--tap-min));\n}\n\n/* Skip-link',
     '    width: var(--tap-min); height: var(--tap-min);\n}\n\n/* Skip-link'),

    ('la fila-puerta vuelve a escribir el suelo a mano',
     CSS,
     'width: 100%; min-height: var(--tap-min); margin-top: var(--sp-10);',
     'width: 100%; min-height: 48px; margin-top: var(--sp-10);'),

    ('«añadir más productos» vuelve a escribirlo a mano',
     CSS,
     'width: 100%; min-height: var(--tap-min); margin-top: var(--sp-14);',
     'width: 100%; min-height: 48px; margin-top: var(--sp-14);'),

    # ── Los antetítulos ──────────────────────────────────────────────────────────────────────
    ('vuelve el antetítulo a la pantalla de entrar',
     LOGIN,
     '        <div class="auth__head">\n            <h2 class="auth__title">',
     '        <div class="auth__head">\n            <span class="eyebrow">{{ a(\'login.eyebrow\') }}</span>\n            <h2 class="auth__title">'),

    ('alguien «termina el trabajo» y borra también el antetítulo de la WEB',
     LANG_ES,
     "        'eyebrow' => 'Nueva contraseña',\n",
     ''),

    # ── La fila del documento legal ──────────────────────────────────────────────────────────
    ('el descargo vuelve a escribirse a mano en una pantalla',
     PRIVACY_ZONE,
     "<WaiverDoc :document=\"waiver.document\" :label=\"a('register.waiver_read')\" />",
     "<details class=\"form__hint\"><summary>{{ a('register.waiver_read') }}</summary></details>"),

    ('la fila del descargo copia la receta en vez de compartirla',
     WAIVER,
     '<summary class="cal-more legal-more">',
     '<summary class="legal-more">'),

    ('la fila del descargo pierde su chevron, y deja de decir que despliega aquí',
     WAIVER,
     '<svg class="cal-more__chev" viewBox="0 0 24 24"',
     '<svg class="cal-more__chevron" viewBox="0 0 24 24"'),

    ('`.legal-more` deja de compartir la receta y se declara su propio alto',
     CSS,
     '.legal-more { margin-top: 0; }',
     '.legal-more { margin-top: 0; min-height: 40px; }'),

    ('el aviso de privacidad recupera su enlace dentro de la frase',
     LANG_ES,
     "'privacy_notice' => 'Al crear tu cuenta tratamos tus datos según nuestra política de privacidad.',",
     "'privacy_notice' => 'Al crear tu cuenta tratamos tus datos según nuestra <a href=\"/privacidad\">política de privacidad</a>.',"),

    ('el alta pierde la fila que abre la política, y el aviso se queda sin documento',
     REGISTER,
     "<span>{{ a('register.privacy_read') }}</span>",
     '<span>{{ a(\'register.title\') }}</span>'),

    ('el control dentro de una pista vuelve a ser invisible (el defecto de `#350`)',
     CSS,
     '.form__hint button { color: var(--interactive); text-decoration: underline; }',
     '.form__hint button { color: var(--fg-mute); }'),
]


def corre_en_verde() -> bool:
    """`True` si las guardas pasan. El veredicto es el CÓDIGO DE SALIDA."""
    hecho = subprocess.run(
        ['php', 'artisan', 'test', '--filter', GUARDAS],
        cwd=RAIZ, capture_output=True, text=True, check=False,
    )

    return hecho.returncode == 0


def main() -> int:
    # ⚠️ Sin verde de partida no se mide nada: una guarda ya roja «muerde» con cualquier mutación.
    print('· comprobando que las guardas salen VERDES antes de mutar…')
    if not corre_en_verde():
        print('✗ las guardas ya están en ROJO con el árbol limpio. No se puede medir nada.')
        return 1

    muerden = 0

    for nombre, fichero, buscar, sustituir in MUTACIONES:
        ruta = RAIZ / fichero
        original = ruta.read_text()

        if buscar not in original:
            print(f'✗ {nombre}\n    el ancla no existe en {fichero} — la mutación no muta nada')
            continue

        ruta.write_text(original.replace(buscar, sustituir, 1))
        try:
            rojo = not corre_en_verde()
        finally:
            ruta.write_text(original)

        muerden += rojo
        print(f'{"✓" if rojo else "✗"} {nombre}')

    print(f'\n{muerden}/{len(MUTACIONES)} mutaciones muerden')

    # El bundle SSR queda rancio tras mutar `.vue`: se reconstruye o la suite entera sale roja
    # con el árbol limpio (`#554`, 35 casos).
    print('· reconstruyendo los dos bundles…')
    subprocess.run(['npm', 'run', 'build'], cwd=RAIZ, capture_output=True, check=False)
    subprocess.run(['npm', 'run', 'build:ssr'], cwd=RAIZ, capture_output=True, check=False)

    return 0 if muerden == len(MUTACIONES) else 1


if __name__ == '__main__':
    sys.exit(main())
