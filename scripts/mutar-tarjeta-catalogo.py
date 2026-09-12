#!/usr/bin/env python3
"""
ARNÉS DE MUTACIÓN de la tarjeta grande del catálogo — ¿muerden las guardas de la T4·3?

Cada mutación reproduce una forma distinta de deshacer la tanda o de relajar su red:

   1. la sección deja de recortar su franja        → «each section is a card»
   2. se cae la franja de tinta                    → «the two band surfaces are declared…»
   3. el recuento deja de seguir a su franja       → lo mismo (la pastilla clara sobre tinta)
   4. la puerta deja de plegar                     → «the door folds and announces it»
   5. `.is-open` deja de abrir el cuerpo           → lo mismo (el catálogo a altura 0, que ya pasó)
   6. la cabecera deja de ser un `<button>`        → lo mismo (ni teclado ni lector de pantalla)
   7. se cae el `aria-expanded`                    → lo mismo (el estado viviría solo en una clase)
   8. la puerta cerrada pierde su alto             → «the head has its two layouts»
   9. la sección abierta no se aplana              → lo mismo
  10. abrir una NO cierra la otra                  → el caso de JS de la exclusividad
  11. buscar deja de ABRIR                         → el caso de JS de la búsqueda
  12. la unidad del pack vuelve dentro del precio  → «the pack unit is a sibling…» (el defecto medido)
  13. falta la frase en un idioma                  → «the band phrase has text in the three locales»
  14. las DOS franjas serían de tinta              → el caso de JS del módulo
  15. `.catalog__per` pierde su regla propia       → `SidebarStyleWiringTest` (salió de su lista de huecos)
  16. el localizador del árbol se queda sin sujeto → «the scan sees its subject»

⚠️ **Exige el árbol COMMITEADO**: el arnés restaura con `git checkout` (`#448`).
⚠️⚠️ **Y después de correrlo, `npm run build:ssr` ANTES DE LA SUITE**: muta ficheros `.vue`, y
`SidebarDomContractTest` renderiza el bundle SSR, no las fuentes (`#551`).
⚠️ Cada mutación COMPRUEBA que el fichero cambió antes de correr el caso (`#500`).
⚠️ El veredicto es el CÓDIGO DE SALIDA, nunca un `grep` de «passed».

    python3 scripts/mutar-tarjeta-catalogo.py
"""
import subprocess
import sys
from pathlib import Path

SITE = Path('public/css/site.css')
VUE = Path('resources/js/sidebar/steps/CatalogStep.vue')
CATALOGO = Path('resources/js/sidebar/catalog.js')
ES = Path('lang/es/tickets.php')
MANIFIESTO = Path('tests/Fixtures/sidebar-dom-manifest.json')
GUARDA = Path('tests/Feature/Architecture/SidebarCatalogCardTest.php')

PHP = 'SidebarCatalogCardTest|SidebarStyleWiringTest'

# (rótulo, motor, fichero, antes, después)
MUTACIONES = [
    ('la sección deja de recortar su franja', 'php', SITE,
     '.catalog-acc__sec { border: 1px solid var(--line); border-radius: var(--r); overflow: hidden; background: var(--bg-card); }',
     '.catalog-acc__sec { border: 1px solid var(--line); border-radius: var(--r); background: var(--bg-card); }'),

    ('se cae la franja de tinta', 'php', SITE,
     '.catalog-acc__sec--ink .catalog-acc__head { background: var(--fg); color: var(--bg); }',
     '/* la franja de tinta, retirada por la mutación */'),

    ('el recuento deja de seguir a su franja', 'php', SITE,
     '.catalog-acc__sec--ink .catalog-acc__count { background: color-mix(in srgb, var(--bg) 18%, transparent); color: var(--bg); }',
     '/* el recuento sobre tinta, retirado por la mutación */'),

    # ⚠️ Estas dos decían lo CONTRARIO hasta `#553`: `#552` retiró un plegado que nunca plegaba y su
    # arnés vigilaba que no volviera. El owner lo devolvió **con su motivo**, así que las mutaciones
    # cambian de sentido con su guarda. *Un arnés hereda la premisa del caso que muta.*
    ('la puerta deja de plegar', 'php', SITE,
     'display: grid; grid-template-rows: 0fr; padding: 0 var(--sp-12);',
     'display: grid; grid-template-rows: 1fr; padding: 0 var(--sp-12);'),

    ('`.is-open` deja de abrir el cuerpo', 'php', SITE,
     '.catalog-acc__sec.is-open .catalog-acc__body { grid-template-rows: 1fr; padding: var(--sp-12); }',
     '.catalog-acc__sec.is-open .catalog-acc__body { padding: var(--sp-12); }'),

    ('la cabecera deja de ser un `<button>`', 'php', VUE,
     '<button type="button" class="catalog-acc__head"',
     '<div type="button" class="catalog-acc__head"'),

    ('se cae el `aria-expanded` de la puerta', 'php', VUE,
     ":aria-expanded=\"seVe(section) ? 'true' : 'false'\"",
     ':data-abierta="seVe(section)"'),

    ('la puerta cerrada pierde su alto', 'php', SITE,
     'gap: var(--sp-8); min-height: 152px; padding: var(--sp-20) var(--sp-16);',
     'gap: var(--sp-8); padding: var(--sp-20) var(--sp-16);'),

    ('la sección abierta no se aplana', 'php', SITE,
     'flex-direction: row; align-items: center;\n    gap: var(--sp-12); min-height: 0;',
     'align-items: center;\n    gap: var(--sp-12); min-height: 0;'),

    ('abrir una NO cierra la otra', 'js', CATALOGO,
     'return abierta === key ? \'\' : key;',
     'return key;'),

    ('buscar deja de ABRIR', 'js', CATALOGO,
     'return hayBusqueda ? casa : abierta === key;',
     'return abierta === key;'),

    # ⚠️ Se muta el ÁRBOL CONGELADO y no la plantilla: lo que esta guarda comprueba es quién acaba
    # siendo hijo de quién, y eso vive en el manifiesto. Mutar el `.vue` probaría otra cosa —que el
    # diff de árbol se entera—, que ya vigila `SidebarDomContractTest`.
    ('la unidad del pack vuelve dentro del precio', 'php', MANIFIESTO,
     '<span class=catalog__price>\\n              <span class=price__from>\\n            <span class=catalog__per>',
     '<span class=catalog__price>\\n              <span class=price__from>\\n              <span class=catalog__per>'),

    ('falta la frase en un idioma', 'php', ES,
     "'section_services_sub' => 'La zona entera para vosotros',",
     "'section_services_sub_retirada_por_la_mutacion' => 'La zona entera para vosotros',"),

    ('las DOS franjas serían de tinta', 'js', CATALOGO,
     "{ key: 'entries', ink: SECCION_EN_TINTA === 'entries',",
     "{ key: 'entries', ink: true,"),

    ('`.catalog__per` pierde su regla propia', 'php', SITE,
     '.catalog__per { font-family: var(--font-body); font-size: var(--fs-body-s); font-weight: var(--fw-semibold); color: var(--fg-mute); line-height: 1.2; }',
     '/* la regla de la unidad, retirada por la mutación */'),

    ('el localizador del árbol se queda sin sujeto', 'php', GUARDA,
     "private const CASO = 'test_the_catalog_step_emits_the_same_tree_in_both_engines#1';",
     "private const CASO = 'test_que_no_existe#1';"),
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
