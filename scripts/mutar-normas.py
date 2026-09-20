#!/usr/bin/env python3
"""Arnés de mutación de `#533` — la página `/normas` rehecha desde su artboard (Fase 3 · T3b): las
normas agrupadas por MOMENTO con su porqué, la escala de altura sacada del dato, la chapa del
descargo y la fecha de la última revisión.

Mismo molde endurecido que `mutar-precios.py` (`#531`) y `mutar-cumple.py` (`#528`):
  · en PYTHON, y cada mutación verifica que el fichero CAMBIÓ antes de correr nada;
  · EXIGE VERDE antes de mutar (`#337`) y ÁRBOL LIMPIO en los ficheros que muta (`#181`);
  · restaura SIEMPRE, también si el proceso revienta (`#448`).

⚠️⚠️ **Desde `#655` (F5 · T2b) la VISTA de PlayJump vive en la instancia** y las guardas se parten por lo
que afirman (`#649`): `RulesPageTest` afirma sobre los DATOS de la vista (`board`, `scale`,
`waiverEnabled`) y mata los mutantes del servicio, del modelo y del controlador; los mutantes de VISTA
apuntan al ANFITRIÓN MÍNIMO del producto (`anfitrion/normas.blade.php`) y los mata `AnfitrionNormasTest`.
El de «vuelve el pliego de tarjetas» se fue con la vista: era diseño, y su juez es la huella.

    python3 scripts/mutar-normas.py
"""
import subprocess
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
FILTRO = 'RulesPageTest|AnfitrionNormasTest'
SERVICIO = 'app/Domain/Content/Services/RuleBoard.php'
MODELO = 'app/Domain/Content/Models/VenueRule.php'
VISTA = 'resources/views/anfitrion/normas.blade.php'
CONTROLADOR = 'app/Http/Controllers/PageController.php'
FICHEROS = [SERVICIO, MODELO, VISTA, CONTROLADOR]

# (nombre, fichero, texto que se busca, texto por el que se cambia[, cuántas veces se espera])
#
# ⚠️⚠️ **EL RECUENTO ES PARTE DE LA MUTACIÓN, y lo enseñó esta tanda.** El molde exigía UNA
# coincidencia y la mutación del porqué salió «NO APLICADA (2)»: su bloque está dos veces en la
# vista —los grupos y las normas sin agrupar—, así que el arnés la dio por superviviente sin haber
# cambiado nada. Las dos copias son **la misma regla**, y mutar una sola dejaría la otra sosteniendo
# la página: se mutan las DOS, y el número se declara aquí para que una copia nueva no pase de largo.
MUTACIONES = [
    # ── Que ninguna norma se pierda ──
    ("una norma sin momento desaparece de la página",
     VISTA,
     "@if ($board['ungrouped']->isNotEmpty())",
     "@if (false)"),

    ("un momento que el producto ya no declara hace desaparecer la norma",
     MODELO,
     "return in_array($this->moment, self::MOMENTS, true) ? $this->moment : null;",
     "return $this->moment;"),

    # ── El orden de los grupos ──
    ("el orden de los grupos lo manda el panel y no la constante",
     SERVICIO,
     "foreach (VenueRule::MOMENTS as $moment) {",
     "foreach ($porMomento->keys()->filter(fn ($k) => $k !== '')->all() as $moment) {"),

    # ── El porqué ──
    ("el porqué se pinta aunque la norma no lo tenga (las DOS copias)",
     VISTA,
     "@if ($rule->tr('reason'))",
     "@if (true)",
     2),

    # ── La fecha ──
    ("la fecha de revisión pasa a ser la de HOY",
     SERVICIO,
     "'updatedAt' => $rules->max('updated_at'),",
     "'updatedAt' => \\Illuminate\\Support\\Carbon::now(),"),

    # ── La escala de altura ──
    # ⚠️ Estos dos apuntaban al `heightScale()` de antes de `#589` (una banda por zona) y salían «NO
    # APLICADA» desde entonces; re-apuntados en `#655`, cuando `RulesPageTest` pasó a afirmar sobre `scale`.
    ("el eje se pinta aunque ninguna zona declare altura",
     SERVICIO,
     "if ($tramos === []) {\n            return null;\n        }",
     "if (false) {\n            return null;\n        }"),

    ("la banda «hasta» se dibuja como si fuera «a partir de»",
     SERVICIO,
     "$hasta = $min !== null ? $techo : min((int) $max, $techo);",
     "$hasta = $techo;"),

    # ── La chapa del descargo ──
    ("la chapa del descargo deja de seguir al ajuste del pie",
     CONTROLADOR,
     "'waiverEnabled' => WaiverSettings::isEnabled(),",
     "'waiverEnabled' => true,"),

    # ── La fecha, ESCRITA (`#656`) ──
    ("la fecha vuelve a escribirse a mano en la vista (y en inglés dice «September de 2026»)",
     VISTA,
     "\\App\\Domain\\Platform\\Services\\LocalDate::monthYear($board['updatedAt'])",
     "$board['updatedAt']->translatedFormat('F \\d\\e Y')"),
]


def git(*args):
    return subprocess.run(['git', *args], cwd=RAIZ, capture_output=True, text=True)


def restaura():
    git('checkout', '-q', '--', *FICHEROS)


def verde():
    r = subprocess.run(
        ['docker', 'compose', 'exec', '-u', 'sail', '-T', 'laravel.test',
         'php', 'artisan', 'test', '--filter=' + FILTRO],
        cwd=RAIZ, capture_output=True, text=True)
    return r.returncode == 0, r.stdout + r.stderr


def main():
    sucio = git('status', '--porcelain', '--', *FICHEROS).stdout.strip()
    if sucio:
        print('✗ hay cambios sin commitear en los ficheros que se mutan — commitea antes:')
        print(sucio)
        return 2

    print('── CONTROL: la guarda tiene que estar VERDE antes de mutar ──')
    ok, salida = verde()
    if not ok:
        print('✗ el árbol limpio ya sale ROJO: el arnés no mide nada')
        print(salida[-1500:])
        return 2
    print('✓ verde\n')

    print('── mutaciones ──')
    vivas = []
    try:
        for mutacion in MUTACIONES:
            nombre, rel, busca, cambia = mutacion[:4]
            # Cuántas copias se esperan: una salvo que la mutación declare otra cosa.
            esperadas = mutacion[4] if len(mutacion) > 4 else 1
            ruta = RAIZ / rel
            antes = ruta.read_text(encoding='utf-8')
            n = antes.count(busca)
            if n != esperadas:
                # ⚠️ Una mutación que no se aplica NO es una guarda que aguanta — y una que se aplica
                # a MENOS copias de las que hay tampoco: la copia intacta sostiene la página.
                print('  ⚠ NO APLICADA (%d coincidencias, se esperaban %d)  %s' % (n, esperadas, nombre))
                vivas.append(nombre + ' [no aplicada]')
                continue
            ruta.write_text(antes.replace(busca, cambia, esperadas), encoding='utf-8')
            assert ruta.read_text(encoding='utf-8') != antes, 'el fichero no cambió'

            ok, _ = verde()
            print(('  ✗ SOBREVIVE  ' if ok else '  ✓ muere      ') + nombre)
            if ok:
                vivas.append(nombre)
            restaura()
    finally:
        restaura()

    print('\n' + '─' * 60)
    print('%d/%d mutaciones mueren' % (len(MUTACIONES) - len(vivas), len(MUTACIONES)))
    for v in vivas:
        print('   sobrevive: ' + v)
    return 1 if vivas else 0


if __name__ == '__main__':
    sys.exit(main())
