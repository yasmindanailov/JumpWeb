#!/usr/bin/env python3
"""
ARNÉS DE MUTACIÓN de la grieta 00 — ¿muerde `SidebarBodySizeTest`?

En este repo una guarda no vale hasta que se la ve morder con el fallo REAL que la motivó: las dos
últimas nacieron laxas y lo dijo el arnés, no una relectura (`#525`, `#485`).

Cada mutación reproduce una forma distinta de deshacer la tanda:

  1. una regla del cajón vuelve a 13 px                     → «no rule declares a size below the scale»
  2. una regla del cajón estrena un LITERAL por debajo      → lo mismo, por la puerta del literal
  3. se cae una línea del bloque acotado                    → «every shared class is raised only…»
  4. la regla BASE de una compartida se sube «para arreglar» → «the shared rules do not leak out…»
  5. desaparece el sujeto de la excepción declarada         → «the exception list still has a subject»
  6. una familia que el primer censo perdió vuelve a 12     → prueba que los prefijos nuevos vigilan
  7. el escáner se queda sin corpus                         → «the scanner sees the drawer»

⚠️ **Exige el árbol COMMITEADO**: el arnés restaura desde `git checkout`, y `#448` dejó el árbol
mutado dos veces por confiar en un `trap` que no corre con SIGKILL. Si hay cambios sin commitear,
para.
⚠️ **Cada mutación COMPRUEBA que el fichero cambió** antes de correr el caso: en `#500` cinco
mutaciones «sobrevivieron» sin haberse aplicado nunca.
⚠️ El veredicto es el CÓDIGO DE SALIDA de la suite, nunca un `grep` de «passed».

    python3 scripts/mutar-cuerpo-cajon.py
"""
import subprocess
import sys
from pathlib import Path

FILTRO = 'SidebarBodySizeTest'
HOJA = Path('public/css/site.css')

# (rótulo, fichero, antes, después)
MUTACIONES = [
    # ⚠️ El texto se copia de la HOJA, no se escribe de memoria: la primera versión se dejó el
    # `flex: none` y la mutación **no se aplicó nunca** — y el arnés lo dijo («no se encontró el
    # texto a mutar»), que es justo para lo que lleva esa comprobación.
    ('una regla del cajón vuelve a 13', HOJA,
     '.cart__price { flex: none; font-family: var(--font-body); font-weight: var(--fw-bold); font-size: var(--fs-body);',
     '.cart__price { flex: none; font-family: var(--font-body); font-weight: var(--fw-bold); font-size: var(--fs-13);'),

    ('una regla del cajón estrena un literal de 12px', HOJA,
     '.orders__code { font-family: var(--font-body); font-size: var(--fs-body);',
     '.orders__code { font-family: var(--font-body); font-size: 12px;'),

    ('se cae una línea del bloque acotado', HOJA,
     '.sidecart__panel .check { font-size: var(--fs-body); }',
     '/* .sidecart__panel .check — retirada por la mutación */'),

    ('la regla BASE de una compartida se sube', HOJA,
     '.check { display: flex; align-items: flex-start; gap: var(--sp-10); font-family: var(--font-body); font-size: var(--fs-13);',
     '.check { display: flex; align-items: flex-start; gap: var(--sp-10); font-family: var(--font-body); font-size: var(--fs-body);'),

    ('desaparece el sujeto de la excepción', HOJA,
     '.acct__alert-ico {',
     '.acct__alert-ico-retirada-por-la-mutacion {'),

    ('una familia del censo perdido vuelve a 12', HOJA,
     '.qr-pass__notice {',
     '.qr-pass__notice { font-size: var(--fs-12);'),

    # ⚠️⚠️ La primera versión sustituía TRES prefijos y la mutación **SOBREVIVÍA**: con los otros
    # veintiuno el censo seguía pasando de cien reglas, así que no probaba nada. *Una mutación que no
    # muerde puede ser DÉBIL antes que reveladora.* Se vacía la lista entera.
    ('el escáner se queda sin corpus', Path('tests/Feature/Architecture/SidebarBodySizeTest.php'),
     "        'acc-tile', 'acct', 'account__', 'addons__', 'auth__', 'bk-', 'cal-more', 'cal__', 'cart',\n"
     "        'cartbar', 'catalog', 'daystrip', 'dep-pick', 'entry__', 'guardnote', 'orders__', 'paydue',\n"
     "        'prod-ico', 'purchase', 'qr-pass', 'qtybox', 'timestrip', 'whoblock', 'wiz__',",
     "        'zzz-no-existe',"),
]


def corre(cmd: list[str]) -> int:
    return subprocess.run(cmd, capture_output=True, text=True).returncode


def suite_en_verde() -> bool:
    return corre(['docker', 'compose', 'exec', '-u', 'sail', '-T', 'laravel.test',
                  'php', 'artisan', 'test', '--filter', FILTRO]) == 0


def main() -> int:
    if subprocess.run(['git', 'status', '--porcelain'], capture_output=True, text=True).stdout.strip():
        print('✗ El árbol tiene cambios sin commitear. Commitea antes de mutar: el arnés restaura con git.')
        return 1

    print(f'CONTROL · ¿la guarda pasa con el árbol sano? ', end='', flush=True)
    if not suite_en_verde():
        print('NO → el arnés no puede decir nada. Arregla la guarda primero.')
        return 1
    print('sí')

    muerden = 0

    for rotulo, fichero, antes, despues in MUTACIONES:
        texto = fichero.read_text(encoding='utf-8')

        if antes not in texto:
            print(f'✗ {rotulo}: no se encontró el texto a mutar — la mutación NO se aplicó (revísala)')
            continue

        fichero.write_text(texto.replace(antes, despues, 1), encoding='utf-8')

        if fichero.read_text(encoding='utf-8') == texto:
            print(f'✗ {rotulo}: el fichero no cambió')
            continue

        muerde = not suite_en_verde()
        subprocess.run(['git', 'checkout', '--', str(fichero)], check=True)

        print(f'{"✓" if muerde else "✗"} {rotulo}{"" if muerde else "  ← SOBREVIVE: la guarda no lo ve"}')
        muerden += int(muerde)

    print(f'\n{muerden}/{len(MUTACIONES)} mutaciones muerden')
    return 0 if muerden == len(MUTACIONES) else 1


if __name__ == '__main__':
    sys.exit(main())
