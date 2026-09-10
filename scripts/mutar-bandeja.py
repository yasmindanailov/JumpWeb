#!/usr/bin/env python3
"""Arnés de mutación de `#506` — la BANDEJA (asunto + línea de adelanto).

⚠️ En PYTHON y no en bash a propósito, y se pagó averiguarlo: la primera versión mutaba con
heredocs anidados dentro de `bash`, el escapado de `$` se perdía por el camino y **cinco
mutaciones «sobrevivieron» sin haberse aplicado nunca**. Es la lección ya fichada de esta casa
—*cuando un instrumento dice que nada funciona, la primera hipótesis es el instrumento*—, y aquí
cada mutación **verifica que el fichero cambió** antes de correr nada.

Las otras tres reglas pagadas:
  · EXIGE VERDE antes de mutar (`#337`): un filtro que no casa con ningún test sale con código ≠ 0
    y el arnés cantaría «muerde» sin haber ejecutado un solo caso;
  · EXIGE ÁRBOL LIMPIO (`#181`): restaurar con `git checkout` se lleva el trabajo sin commitear;
  · restaura SIEMPRE, también si el proceso revienta (`#448`).
"""
import subprocess
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
FILTRO = 'MailInboxLineTest'
FICHEROS = [
    'lang/es/account.php',
    'lang/es/emails.php',
    'app/Notifications/VerifyEmailForPurchase.php',
    'resources/views/vendor/mail/html/layout.blade.php',
    'resources/views/vendor/mail/text/message.blade.php',
    'app/Notifications/Support/BrandedMailMessage.php',
]

# (nombre, fichero, texto que se busca, texto por el que se cambia)
MUTACIONES = [
    ("badge/headline vuelven dentro de providers (el defecto REAL de #504)",
     'lang/es/account.php',
     "        'badge' => 'Cuenta vinculada',\n        'headline' => 'Has vinculado una cuenta',\n",
     ""),

    ("el asunto pide :code y su __() no lo pasa (el defecto REAL de esta tanda)",
     'app/Notifications/VerifyEmailForPurchase.php',
     "__('emails.verify_purchase.subject', ['code' => $this->orderCode])",
     "__('emails.verify_purchase.subject')"),

    ("una línea de adelanto gana un :code, que saldría literal en la bandeja",
     'lang/es/emails.php',
     "'preheader' => 'Si hay dinero que devolver",
     "'preheader' => 'Reserva :code. Si hay dinero que devolver"),

    ("una línea de adelanto pasa del tope de la previsualización",
     'lang/es/emails.php',
     "'preheader' => 'Dentro tienes qué has añadido o quitado y lo que se paga en el parque.'",
     "'preheader' => 'Dentro tienes que has anadido o quitado y lo que se paga en el parque el dia de la fiesta con el resto.'"),

    ("un asunto recupera el nombre del parque, que el remitente ya dice al lado",
     'lang/es/emails.php',
     "'subject' => 'Reserva cancelada · :code'",
     "'subject' => 'Reserva cancelada en :park · :code'"),

    ("la línea de adelanto deja de pintarse en el layout",
     'resources/views/vendor/mail/html/layout.blade.php',
     "@isset($preheader)",
     "@isset($noExisteEstaVariable)"),

    ("la línea se cuela en la parte de texto plano",
     'resources/views/vendor/mail/text/message.blade.php',
     "    {{ $slot }}",
     "    {{ $preheader }}\n    {{ $slot }}"),

    ("hero() deja de derivar la línea: los 21 se quedan sin ella a la vez",
     'app/Notifications/Support/BrandedMailMessage.php',
     "if (Lang::has($grupo.'.preheader')) {",
     "if (false) {"),

    ("hero() la deriva SIN comprobar que la clave existe (la clave saldría cruda)",
     'app/Notifications/Support/BrandedMailMessage.php',
     "if (Lang::has($grupo.'.preheader')) {",
     "if (true) {"),
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
                # ⚠️ Una mutación que no se aplica NO es una guarda que aguanta.
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
