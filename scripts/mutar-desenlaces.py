#!/usr/bin/env python3
"""Arnés de mutación de LA PARADA 04 — el salto al banco y los cuatro desenlaces (`#563`).

Cada mutación es un defecto REAL que alguien podría cometer al tocar estas pantallas. Si una NO
muerde, la guarda que debía cazarla no vale y hay que rehacerla — no bajar la mutación.

    docker compose exec -u sail -T laravel.test python3 scripts/mutar-desenlaces.py

⚠️⚠️ **EL BUNDLE SSR QUEDA RANCIO AL TERMINAR** si se interrumpe: este arnés muta `.vue` y `.js` que
compila Vite, y `SidebarDomContractTest` renderiza el BUNDLE, no las fuentes. Restaurar el árbol NO
restaura el bundle — la trampa que `#551` y `#554` pagaron, con 35 casos en rojo y el árbol limpio.
Este guion lo reconstruye él mismo al final.

⚠️ **El veredicto es el CÓDIGO DE SALIDA**, nunca un `grep` de «passed».
"""

import subprocess
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent

VERIFY = 'resources/js/sidebar/steps/VerifyStep.vue'
VERIFYING = 'resources/js/sidebar/steps/VerifyingStep.vue'
CONFIRMED = 'resources/js/sidebar/steps/ConfirmedStep.vue'
DECLINED = 'resources/js/sidebar/steps/DeclinedStep.vue'
OUTCOME = 'resources/js/sidebar/outcome.js'
STORE = 'resources/js/sidebar/stores/outcome.js'
SECTION = 'resources/js/sidebar/sections/PurchaseSection.vue'

# El filtro `js` corre `npm run test:js`; cualquier otro, `php artisan test --filter=<x>`.
MUTACIONES = [
    ('la plaza deja de decirse guardada mientras se verifica el correo',
     VERIFY, "<p class=\"purchase__note\">{{ t('verify_hold') }}</p>", '',
     'SidebarDomContractTest'),

    ('«verifica tu correo» estrena una pegatina de ESTADO donde no ha pasado nada',
     VERIFY, '<span class="state-tile">', '<span class="state-badge state-badge--ok">',
     'SidebarDomContractTest'),

    # ⚠️ Estas dos NO las puede cazar el diff de árbol: las dos ramas emiten el MISMO nodo y solo
    # cambia el texto. Las ata el cableado (`SidebarOutcomeParityTest`).
    ('la pantalla deja de decir A QUÉ correo se ha escrito',
     VERIFY, "email ? tp('verify_intro_sent', { email }) : t('verify_intro')", "t('verify_intro')",
     'SidebarOutcomeParityTest'),

    ('el buzón deja de llegarle al paso 7',
     SECTION, ':email="authStore.pendingEmail"', '',
     'SidebarOutcomeParityTest'),

    ('la espera del paso 11 se queda sin su pegatina',
     VERIFYING, '<span class="state-badge state-badge--wait">', '<span hidden>',
     'SidebarDomContractTest'),

    ('la puerta al carné se ofrece SIN sesión, que es donde no hay nada que enseñar',
     CONFIRMED, '<button v-if="hasSession" type="button" class="btn btn--ink btn--lg purchase__cta"',
     '<button type="button" class="btn btn--ink btn--lg purchase__cta"',
     'SidebarDomContractTest'),

    ('los tres importes vuelven a leerse como tres frases sueltas',
     CONFIRMED, '<div class="purchase__money">', '<div>',
     'SidebarDomContractTest'),

    ('el denegado recupera el botón que manda a reservar otra cosa',
     DECLINED, '<a :href="contactUrl" class="btn btn--ghost purchase__cta-tertiary">',
     '<button type="button" class="btn btn--ghost purchase__cta-secondary">x</button><a :href="contactUrl" class="btn btn--ghost purchase__cta-tertiary">',
     'SidebarDomContractTest'),

    ('una fecha ROTA se pinta en vez de callarse, dentro de una promesa de plazo',
     OUTCOME, 'if (Number.isNaN(instante.getTime())) return \'\';',
     'if (Number.isNaN(instante.getTime())) return String(expiresAt);',
     'js'),

    # ⚠️ **La primera versión de esta mutación era DÉBIL y no mordía**: cambiaba el `status === null`
    # por `status?.expires_at`, y con `status` nulo eso da `undefined`, que `holdUntilLabel()` ya
    # convierte en cadena vacía. O sea, dos capas defendiendo lo mismo — y una mutación que no
    # distingue dos mundos no prueba nada. La que sí distingue es ésta: la hora SIN formatear.
    ('la hora va a pantalla SIN formatear, con el ISO del contrato dentro de la promesa',
     STORE, 'holdUntilLabel(status.expires_at, locale)', "(status.expires_at ?? '')",
     'js'),

    ('la hora de caducidad sobrevive al barrido y la arrastra la compra siguiente',
     STORE, "            this.holdUntil = '';\n", '',
     'js'),
]


def construir_ssr() -> None:
    subprocess.run(['npm', 'run', 'build:ssr'], cwd=RAIZ, capture_output=True, check=False)


def corre_en_verde(filtro: str) -> bool:
    """`True` si la guarda pasa. El veredicto es el CÓDIGO DE SALIDA."""
    orden = ['npm', 'run', 'test:js'] if filtro == 'js' else ['php', 'artisan', 'test', '--filter', filtro]
    hecho = subprocess.run(orden, cwd=RAIZ, capture_output=True, text=True, check=False)

    return hecho.returncode == 0


def main() -> int:
    # ⚠️ Sin verde de partida no se mide nada: una guarda ya roja «muerde» con cualquier mutación.
    print('· comprobando que las guardas salen VERDES antes de mutar…')
    construir_ssr()
    for filtro in sorted({m[4] for m in MUTACIONES}):
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
            if filtro != 'js':
                construir_ssr()
            rojo = not corre_en_verde(filtro)
        finally:
            ruta.write_text(original)

        muerden += rojo
        print(f'{"✓" if rojo else "✗"} {nombre}  →  {filtro}')

    construir_ssr()

    print(f'\n{muerden}/{len(MUTACIONES)} mutaciones muerden')

    return 0 if muerden == len(MUTACIONES) else 1


if __name__ == '__main__':
    sys.exit(main())
