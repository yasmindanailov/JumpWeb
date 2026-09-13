#!/usr/bin/env python3
"""Arnés de mutación de `#567`: los tres arreglos del SPA que pidió el owner.

  1. el mismo menor en DOS entradas del pedido se compra (el `distinct` global daba 422);
  2. la reserva creada sin el bloque de cuenta y con «Ir a mi cuenta» → índice del área;
  3. «¿Quiénes vienen?» en una fila, con rótulos cortos y un quinto para el bloque de solo justificante.

Cada mutación es un defecto REAL que alguien podría cometer. Si una NO muerde, la guarda que debía
cazarla no vale y hay que rehacerla — no bajar la mutación.

    docker compose exec -u sail -T laravel.test python3 scripts/mutar-567.py

⚠️ **El veredicto es el CÓDIGO DE SALIDA** de `artisan test` y de `node --test`, nunca un `grep` de
«passed»: un filtro que no casa con ningún test sale con código 0 y diría que la mutación no muerde.

⚠️⚠️ **Muta ficheros `.vue`, así que deja el BUNDLE SSR rancio** (`#554`): las guardas que mide leen la
FUENTE, y al terminar se reconstruyen los dos bundles igualmente.

⚠️ **Restaura desde el contenido LEÍDO, nunca con `git checkout`** (la regla de `#181`). Una
interrupción dura (SIGKILL) dejaría el fichero mutado: antes de correrlo, el árbol va commiteado o con
un parche de respaldo (`git diff > …`) del que se pueda volver.
"""

import subprocess
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent

PAYLOAD = 'app/Http/Api/CartPayload.php'
CSS = 'public/css/site.css'
CONFIRMED = 'resources/js/sidebar/steps/ConfirmedStep.vue'
PURCHASE = 'resources/js/sidebar/sections/PurchaseSection.vue'
TIMESTEP = 'resources/js/sidebar/steps/TimeStep.vue'
ASSIGNMENT = 'resources/js/sidebar/assignment.js'

# Se corren juntas: una mutación puede caer en cualquiera, y pedir «cuál» de antemano es cementar a
# qué fichero pertenece cada propiedad.
GUARDAS = ('OrdersDependentAssignmentTest|SidebarCartParityTest|SidebarAccountVisibilityTest'
           '|AccountDoorWiringTest|WhoBlockLabelTest')
JS = 'resources/js/sidebar/assignment.test.js'

# (nombre, fichero, buscar, sustituir)
MUTACIONES = [
    # ── 1 · el mismo menor en dos entradas ───────────────────────────────────────────────────
    ('se quita la regla por línea: un menor repetido en la MISMA entrada vuelve a pasar',
     PAYLOAD,
     "$prefix.'.dependent_ids' => ['sometimes', 'array', 'max:'.self::MAX_LINES, self::noRepeatedDependents(...)],",
     "$prefix.'.dependent_ids' => ['sometimes', 'array', 'max:'.self::MAX_LINES],"),

    ('vuelve el `distinct` de dos comodines, que compara contra la cesta ENTERA',
     PAYLOAD,
     "$prefix.'.dependent_ids.*' => ['integer', 'min:1'],",
     "$prefix.'.dependent_ids.*' => ['integer', 'min:1', 'distinct'],"),

    ('el motivo del rechazo falta en inglés',
     'lang/en/api.php',
     "        'repeated' => 'You have picked the same dependent twice for these tickets.',\n",
     ''),

    # ── 2 · la reserva creada ────────────────────────────────────────────────────────────────
    ('el bloque de cuenta vuelve a verse en el desenlace',
     CSS,
     '.sidecart__panel.is-account .acct,\n.sidecart__panel.is-result .acct {\n    grid-template-rows: 0fr;',
     '.sidecart__panel.is-account .acct {\n    grid-template-rows: 0fr;'),

    ('la gemela de movimiento reducido se queda atrás',
     CSS,
     '    .sidecart__panel.is-account .acct,\n    .sidecart__panel.is-result .acct {\n        transition:',
     '    .sidecart__panel.is-account .acct {\n        transition:'),

    ('alguien oculta también el bloque en el CATÁLOGO (en medio de la lista)',
     CSS,
     '.sidecart__panel.is-booking .acct,\n.sidecart__panel.is-cart .acct,',
     '.sidecart__panel.is-booking .acct,\n.sidecart__panel.is-catalog .acct,\n.sidecart__panel.is-cart .acct,'),

    ('alguien oculta también el bloque en el CATÁLOGO (al PRINCIPIO de la lista)',
     CSS,
     '\n.sidecart__panel.is-booking .acct,\n.sidecart__panel.is-cart .acct,',
     '\n.sidecart__panel.is-catalog .acct,\n.sidecart__panel.is-booking .acct,\n.sidecart__panel.is-cart .acct,'),

    ('el botón emite el evento viejo y nadie lo escucha',
     CONFIRMED,
     "@click=\"$emit('go-account')\"",
     "@click=\"$emit('show-card')\""),

    ('el botón vuelve al rótulo retirado',
     CONFIRMED,
     "{{ t('go_to_account') }}",
     "{{ t('see_my_card') }}"),

    ('la sección deja de escuchar la salida',
     PURCHASE,
     '            @add-another="addAnother"\n            @go-account="goToAccount" />',
     '            @add-another="addAnother" />'),

    ('la salida vuelve a llevar al carné en vez de al índice',
     PURCHASE,
     'function goToAccount() {\n    accountStore.openZone(ZONES.HOME);\n}',
     'function goToAccount() {\n    accountStore.openZone(ZONES.CARD);\n}'),

    ('el rótulo del botón falta en francés',
     'lang/fr/tickets.php',
     "    'go_to_account' => 'Aller à mon compte',\n",
     ''),

    # ── 3 · «¿Quiénes vienen?» ───────────────────────────────────────────────────────────────
    ('se pierde el quinto rótulo: el bloque de solo justificante vuelve a decir «menores»',
     ASSIGNMENT,
     "    if (offers === false) return 'who_block.guardian_only';\n",
     ''),

    ('el quinto rótulo gana al justificante MARCADO',
     ASSIGNMENT,
     "    if (guardian) return 'who_block.guardian';\n    if (offers === false) return 'who_block.guardian_only';",
     "    if (offers === false) return 'who_block.guardian_only';\n    if (guardian) return 'who_block.guardian';"),

    ('el quinto rótulo falta en francés',
     'lang/fr/tickets.php',
     "        'guardian_only' => 'Autorisation',\n",
     ''),

    ('el paso de hora deja de decirle al módulo si hay menores que ofrecer',
     TIMESTEP,
     ', offers: offersDependents.value }), { count',
     ' }), { count'),
]


def corre_en_verde() -> bool:
    php = subprocess.run(
        ['php', 'artisan', 'test', '--filter', GUARDAS],
        cwd=RAIZ, capture_output=True, text=True, check=False,
    )
    if php.returncode != 0:
        return False

    js = subprocess.run(['node', '--test', JS], cwd=RAIZ, capture_output=True, text=True, check=False)

    return js.returncode == 0


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

        if original.count(buscar) != 1:
            print(f'✗ {nombre}\n    el ancla aparece {original.count(buscar)} veces en {fichero} — la mutación no es la que se cree')
            continue

        ruta.write_text(original.replace(buscar, sustituir, 1))
        try:
            rojo = not corre_en_verde()
        finally:
            ruta.write_text(original)

        muerden += rojo
        print(f'{"✓" if rojo else "✗"} {nombre}', flush=True)

    print(f'\n{muerden}/{len(MUTACIONES)} mutaciones muerden')

    # El bundle SSR queda rancio tras mutar `.vue`: se reconstruye o la suite entera sale roja con el
    # árbol limpio (`#554`).
    print('· reconstruyendo los dos bundles…')
    subprocess.run(['npm', 'run', 'build'], cwd=RAIZ, capture_output=True, check=False)
    subprocess.run(['npm', 'run', 'build:ssr'], cwd=RAIZ, capture_output=True, check=False)

    return 0 if muerden == len(MUTACIONES) else 1


if __name__ == '__main__':
    sys.exit(main())
