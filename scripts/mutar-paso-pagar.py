#!/usr/bin/env python3
"""Arnés de mutación de LO QUE FALTA ANTES DE PAGAR (`#562`, paso 08 del cajón).

Cada mutación es un defecto REAL que alguien podría cometer al tocar esta pantalla. Si una NO
muerde, la guarda que debía cazarla no vale y hay que rehacerla — no bajar la mutación.

    docker compose exec -u sail -T laravel.test python3 scripts/mutar-paso-pagar.py

⚠️⚠️ **EL BUNDLE SSR QUEDA RANCIO AL TERMINAR.** Este arnés muta ficheros `.vue` y `.mjs` que
compila Vite, y `SidebarDomContractTest` renderiza el BUNDLE, no las fuentes. Restaurar el árbol
NO restaura el bundle: hay que `npm run build:ssr` después, aunque `git status` salga limpio.
Es la trampa que `#551` y `#554` pagaron —35 casos en rojo con el árbol limpio— y por eso este
guion lo reconstruye él mismo al final.

⚠️ **El veredicto es el CÓDIGO DE SALIDA de `artisan test`**, nunca un `grep` de «passed»: un
filtro que no casa con ningún test sale con código 0 y diría que la mutación no muerde.
"""

import subprocess
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent

LAYOUT = 'resources/views/components/layout.blade.php'
SECTION = 'resources/js/sidebar/sections/PurchaseSection.vue'
PASO = 'resources/js/sidebar/steps/PayStep.vue'
RENDER = 'scripts/render-sidebar.mjs'
CSS = 'public/css/site.css'
LANG = 'lang/es/tickets.php'

# (nombre, fichero, buscar, sustituir, filtro de la guarda que debe ponerse ROJA)
MUTACIONES = [
    ('la URL de las condiciones deja de viajar del servidor',
     LAYOUT, "'terms' => route('legal.condiciones'),", '',
     'SidebarOutcomeParityTest'),

    ('el paso de pagar deja de recibirla',
     SECTION, ':terms-url="urls.terms ?? \'\'"', '',
     'SidebarOutcomeParityTest'),

    ('la fila lleva una ruta QUEMADA en vez de la del servidor',
     PASO, ':href="termsUrl"', 'href="/condiciones"',
     'SidebarOutcomeParityTest'),

    ('el enlace vuelve DENTRO de la frase de la casilla',
     LANG, "'due_terms' => 'He leído y acepto las condiciones de reserva',",
     "'due_terms' => 'He leído y acepto las <a href=\":url\">condiciones de reserva</a>',",
     'SidebarMountTest'),

    ('la fila «Leer las condiciones» desaparece',
     PASO, '<a :href="termsUrl" target="_blank" rel="noopener" class="cal-more paydue__terms">',
     '<a :href="termsUrl" target="_blank" rel="noopener" class="paydue__legal" v-if="false">',
     'SidebarDomContractTest'),

    ('el bloque pierde su rótulo',
     PASO, '<p class="paydue__heading">{{ t(\'due_heading\') }}</p>', '',
     'SidebarDomContractTest'),

    ('el renderizador deja de componer lo que falta, y el árbol se congela sin ello',
     RENDER, 'need: buyerNeeds(api.accountContext ?? null, {}),', '',
     'SidebarDomContractTest'),

    ('la fila copia la receta en vez de compartirla',
     CSS, '.cal-more,\n.paydue__terms {', '.cal-more {',
     'SidebarTokenBudgetTest'),

    ('el rótulo del bloque baja del suelo de la grieta 00',
     CSS, 'font-family: var(--font-mono); font-size: var(--fs-label); font-weight: var(--fw-semibold);\n    letter-spacing: .14em;',
     'font-family: var(--font-mono); font-size: var(--fs-11); font-weight: var(--fw-semibold);\n    letter-spacing: .14em;',
     'SidebarBodySizeTest'),
]


def construir_ssr() -> None:
    subprocess.run(['npm', 'run', 'build:ssr'], cwd=RAIZ, capture_output=True, check=False)


def corre_en_verde(filtro: str) -> bool:
    """`True` si la guarda pasa. El veredicto es el CÓDIGO DE SALIDA."""
    hecho = subprocess.run(
        ['php', 'artisan', 'test', '--filter', filtro],
        cwd=RAIZ, capture_output=True, text=True, check=False,
    )

    return hecho.returncode == 0


def main() -> int:
    # ⚠️ **Sin verde de partida el arnés no mide nada**: una guarda ya roja «muerde» con cualquier
    # mutación. Es la regla que `#337` pagó con un «14 de 14» que eran 13.
    print('· comprobando que las guardas salen VERDES antes de mutar…')
    construir_ssr()
    filtros = sorted({m[4] for m in MUTACIONES})
    for filtro in filtros:
        if not corre_en_verde(filtro):
            print(f'✗ {filtro} ya está en ROJO con el árbol limpio. No se puede medir nada.')
            return 1

    muerden = 0
    for nombre, fichero, buscar, sustituir, filtro in MUTACIONES:
        ruta = RAIZ / fichero
        original = ruta.read_text()

        if buscar not in original:
            print(f'✗ {nombre}\n    el ancla no existe en {fichero} — la mutación no muta nada')
            continue

        ruta.write_text(original.replace(buscar, sustituir, 1))
        try:
            if fichero.endswith(('.vue', '.mjs')):
                construir_ssr()
            rojo = not corre_en_verde(filtro)
        finally:
            ruta.write_text(original)

        muerden += rojo
        print(f'{"✓" if rojo else "✗"} {nombre}  →  {filtro}')

    # El bundle vuelve a quedar al día: el árbol restaurado no basta (ver la cabecera).
    construir_ssr()

    print(f'\n{muerden}/{len(MUTACIONES)} mutaciones muerden')

    return 0 if muerden == len(MUTACIONES) else 1


if __name__ == '__main__':
    sys.exit(main())
