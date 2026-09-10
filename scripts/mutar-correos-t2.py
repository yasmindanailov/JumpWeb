#!/usr/bin/env python3
"""Arnés de mutación de la T2 de los correos — el REMITENTE desde el panel (`DECISIONES #500`).

Comprueba que `tests/Feature/Mail/OutgoingSenderTest.php` MUERDE con cada defecto real puesto.
Una guarda que no se ha visto morder no se sabe si vigila.

⚠️⚠️ ESTE ARNÉS NO USA `git checkout`, a diferencia de los otros del repo, y es deliberado:
aquéllos exigen el árbol commiteado, y un descuido se lleva por delante el trabajo sin commitear
(la lección de `#181`, pagada otra vez en `#317`). Aquí se copia cada fichero antes de tocarlo y se
restaura desde la copia, así que corre con el árbol sucio sin riesgo.

⚠️ Y va en PYTHON y no en bash a propósito: las mutaciones son expresiones sobre el texto del
fichero, y pasarlas por la línea de órdenes convierte cada comilla en un problema de escapado.

Uso:  python3 scripts/mutar-correos-t2.py
"""
from __future__ import annotations

import re
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
SUITE = 'tests/Feature/Mail/OutgoingSenderTest.php'
EXEC = ['docker', 'compose', 'exec', '-u', 'sail', '-T', 'laravel.test']

LISTENER = 'app/Domain/Platform/Listeners/ApplyBusinessSender.php'
SETTING = 'app/Domain/Platform/Models/Setting.php'
PROVIDER = 'app/Providers/AppServiceProvider.php'
PANEL = 'app/Filament/Pages/Settings.php'
FICHEROS = [LISTENER, SETTING, PROVIDER, PANEL]


def verde() -> bool:
    r = subprocess.run(EXEC + ['php', 'artisan', 'test', SUITE],
                       cwd=RAIZ, capture_output=True, text=True)
    return r.returncode == 0


# (nombre, fichero, función que muta el texto)
MUTACIONES = [
    ('el listener no se registra', PROVIDER,
     lambda s: s.replace('Event::listen(MessageSending::class, ApplyBusinessSender::class);', '// mutado')),

    ('no comprueba que el From sea el POR DEFECTO (pisa el explícito)', LISTENER,
     lambda s: re.sub(r'\$porDefecto = .*?\n.*?\n.*?\n.*?\}\n', '', s, count=1, flags=re.S)),

    ('el remitente ignora el ajuste del panel', SETTING,
     lambda s: s.replace("[self::value('mail.from_address', ''), config('mail.from.address')]",
                         "[config('mail.from.address')]")),

    ('no valida la dirección (un ajuste basura rompe el envío)', SETTING,
     lambda s: s.replace(" && filter_var($dir, FILTER_VALIDATE_EMAIL) !== false", '')),

    ('el nombre vuelve a ser el del PRODUCTO', LISTENER,
     lambda s: s.replace('Setting::businessName()', "(string) config('app.name')")),

    ('deja de rendirse cuando hay VARIOS From', LISTENER,
     lambda s: s.replace('if (count($from) !== 1) {', 'if (count($from) < 1) {')),

    ('la clave sale del mapa de ajustes gestionados', PANEL,
     lambda s: s.replace("        'mail.from_address' => 'contact',\n", '')),

    ('el campo del panel deja de validar el formato', PANEL,
     lambda s: re.sub(r"(TextInput::make\('mail\.from_address'\).*?)\n\s*->email\(\)",
                      r'\1', s, count=1, flags=re.S)),
]


def main() -> int:
    tmp = Path(tempfile.mkdtemp())
    copias = {f: tmp / f.replace('/', '_') for f in FICHEROS}
    for f, c in copias.items():
        shutil.copy2(RAIZ / f, c)

    def restaura() -> None:
        for f, c in copias.items():
            shutil.copy2(c, RAIZ / f)

    try:
        print('── CONTROL: la guarda tiene que estar VERDE antes de mutar nada ──')
        if not verde():
            print('✗ la guarda YA está roja: se aborta (un arnés sobre rojo no dice nada)')
            return 1
        print('✓ verde\n')

        muertos = vivos = 0
        for i, (nombre, fichero, mutar) in enumerate(MUTACIONES, 1):
            print(f'{i:2}. {nombre:<58} ', end='', flush=True)
            ruta = RAIZ / fichero
            antes = ruta.read_text(encoding='utf-8')
            despues = mutar(antes)
            if despues == antes:
                print('⚠ LA MUTACIÓN NO CAMBIÓ NADA — el patrón no casa')
                vivos += 1
                continue
            ruta.write_text(despues, encoding='utf-8')
            if verde():
                print('✗ SOBREVIVE')
                vivos += 1
            else:
                print('✓ muere')
                muertos += 1
            restaura()

        print(f'\n── {muertos} muerden · {vivos} sobreviven (de {len(MUTACIONES)}) ──')
        return 0 if vivos == 0 else 1
    finally:
        restaura()
        shutil.rmtree(tmp, ignore_errors=True)


if __name__ == '__main__':
    sys.exit(main())
