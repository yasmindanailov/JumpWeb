#!/usr/bin/env python3
"""Arnés de mutación de `#508` — los dos correos del framework, al molde del producto.

La mutación 1 reproduce **el defecto que los tuvo fuera del carril entero**: que `User` deje de
mandar la nuestra. Sin su caso, eso pasa en verde —las subclases siguen existiendo, así que el
censo del molde no se entera— y el cliente vuelve a recibir un correo que se anuncia con «¡Hola!».

⚠️ Las reglas pagadas: exigir VERDE y ÁRBOL LIMPIO antes de mutar, restaurar siempre, y
**verificar que cada mutación se aplicó** antes de correr un solo caso (`#506`).
"""
import subprocess
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
FILTRO = 'MailMoldTest|MailInboxLineTest|QueuedEmailsTest'
FICHEROS = [
    'app/Domain/Identity/Models/User.php',
    'app/Notifications/PasswordReset.php',
    'app/Notifications/VerifyEmailAddress.php',
    'lang/es/emails.php',
]

MUTACIONES = [
    ("User deja de mandar la nuestra: vuelve la del framework (el defecto REAL)",
     'app/Domain/Identity/Models/User.php',
     "        $this->notify(new VerifyEmailAddress);",
     "        $this->notify(new \\Illuminate\\Auth\\Notifications\\VerifyEmail);"),

    ("…y lo mismo con el enlace de contraseña",
     'app/Domain/Identity/Models/User.php',
     "        $this->notify(new PasswordReset($token));",
     "        $this->notify(new \\Illuminate\\Auth\\Notifications\\ResetPassword($token));"),

    ("el correo de verificación pierde su cabecera",
     'app/Notifications/VerifyEmailAddress.php',
     "            ->hero('emails.verify_email', 'warn')\n",
     ""),

    ("el enlace de contraseña pierde su cabecera",
     'app/Notifications/PasswordReset.php',
     "            ->hero('emails.password_reset', 'warn')\n",
     ""),

    ("uno de los dos deja de encolarse, contra PAY-14",
     'app/Notifications/PasswordReset.php',
     "class PasswordReset extends ResetPassword implements ShouldQueue",
     "class PasswordReset extends ResetPassword"),

    ("se queda sin línea de adelanto en español",
     'lang/es/emails.php',
     "        'preheader' => 'Un clic y tu cuenta queda lista. Si no la has creado tú, ignora este correo.',\n",
     ""),

    ("vuelve a construir el mensaje fuera del molde",
     'app/Notifications/VerifyEmailAddress.php',
     "        return (new BrandedMailMessage)",
     "        return (new \\Illuminate\\Notifications\\Messages\\MailMessage)"),
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

    print('\n' + '─' * 62)
    print('%d/%d mutaciones mueren' % (len(MUTACIONES) - len(vivas), len(MUTACIONES)))
    for v in vivas:
        print('   sobrevive: ' + v)
    return 1 if vivas else 0


if __name__ == '__main__':
    sys.exit(main())
