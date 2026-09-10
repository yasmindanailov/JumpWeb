#!/usr/bin/env python3
"""Arnés de mutación de `#507` — la FORMA del correo de la fiesta mixta.

Las mutaciones no son inventadas: **cinco de las siete reproducen defectos que ocurrieron de
verdad** al construir la tanda —la cifra debajo del libro, el `Htmlable` perdido por un type hint,
el atajo que se salta `formatLine()`, el tono fijo y la tarjeta de producto de vuelta—.

⚠️ Las reglas pagadas de esta casa, las cuatro:
  · EXIGE VERDE antes de mutar (`#337`): un filtro que no casa con ningún test sale con código ≠ 0
    y el arnés cantaría «muerde» sin haber ejecutado un solo caso;
  · EXIGE ÁRBOL LIMPIO (`#181`): restaurar con `git checkout` se lleva el trabajo sin commitear;
  · restaura SIEMPRE, también si el proceso revienta (`#448`);
  · y **cada mutación verifica que el fichero cambió** antes de correr nada (`#506`): un arnés que
    no aplica su mutación informa de que la guarda aguanta cuando no ha medido nada.
"""
import subprocess
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
# ⚠️ Los DOS: la forma del correo vive en `MixedPartyMailShapeTest`, pero `outro()` es pieza del
# MOLDE y su normalización se vigila donde vive el molde. Con un solo filtro, la mutación que se
# salta `formatLine()` sobrevivía por no tener quién la mirara.
FILTRO = 'MixedPartyMailShapeTest|MailMoldTest'
FICHEROS = [
    'app/Notifications/MixedPartySurchargeChanged.php',
    'app/Notifications/Support/BrandedMailMessage.php',
]

# (nombre, fichero, texto que se busca, texto por el que se cambia)
MUTACIONES = [
    ("la cifra vuelve al cuerpo, debajo del libro (el defecto REAL de esta tanda)",
     'app/Notifications/MixedPartySurchargeChanged.php',
     "$message->outro(EmailBookBlock::forOrder($this->item->order));",
     "$message->line(EmailBookBlock::forOrder($this->item->order));"),

    ("`outro()` pierde el Htmlable por un type hint `string` (el defecto REAL)",
     'app/Notifications/Support/BrandedMailMessage.php',
     "public function outro(Htmlable|string $texto): static",
     "public function outro(string $texto): static"),

    ("`outro()` se salta `formatLine()`, que colapsa los saltos de línea",
     'app/Notifications/Support/BrandedMailMessage.php',
     "$this->outroLines[] = $this->formatLine($texto);",
     "$this->outroLines[] = $texto;"),

    ("el tono vuelve a ser `warn` fijo: un descuento llega como «falta algo»",
     'app/Notifications/MixedPartySurchargeChanged.php',
     "$tono = $this->newCents > 0 ? 'warn' : 'info';",
     "$tono = 'warn';"),

    ("la chapa y el aviso dejan de compartir derivación y pueden contradecirse",
     'app/Notifications/MixedPartySurchargeChanged.php',
     "->hero('emails.mixed_party_surcharge', $tono, EmailSlip::forItem($this->item))",
     "->hero('emails.mixed_party_surcharge', 'warn', EmailSlip::forItem($this->item))"),

    ("la entradilla vuelve a repetir el código y el producto del resguardo",
     'app/Notifications/MixedPartySurchargeChanged.php',
     "                : 'emails.mixed_party_surcharge.intro_by_park'));",
     "                : 'emails.mixed_party_surcharge.intro_by_park', ['code' => $code, 'product' => $product])).' '.$code.' '.$product;"),

    ("la cifra deja de ir en el aviso y vuelve a ser un párrafo más",
     'app/Notifications/MixedPartySurchargeChanged.php',
     "        $message->notice(",
     "        $message->line($cifra);\n        $noSePinta = fn () => $message->notice("),
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
        for nombre, rel, busca, cambia in MUTACIONES:
            ruta = RAIZ / rel
            antes = ruta.read_text(encoding='utf-8')
            n = antes.count(busca)
            if n != 1:
                print('  ⚠ NO APLICADA (%d coincidencias)  %s' % (n, nombre))
                vivas.append(nombre + ' [no aplicada]')
                continue
            ruta.write_text(antes.replace(busca, cambia, 1), encoding='utf-8')
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
