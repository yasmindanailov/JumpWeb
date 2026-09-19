#!/usr/bin/env python3
"""Arnés de mutación de la T7·1: el correo del post-form cuando el producto tiene invitación
(`docs/specs/celebracion-e-invitacion.md` §4.9 y §10.14; `DECISIONES #714`).

Cada mutación rompe UNA propiedad de la unidad y comprueba que su guarda se pone en rojo:

  · con invitación la llamada es **compartir**, y sin ella el correo **no cambia ni una palabra**;
  · el botón aterriza **en el bloque** (`#gf-invite`), que en un teléfono empieza fuera de pantalla;
  · el interruptor **no basta**: sin columna de nombre no hay invitación que prometer;
  · y el **asunto no tiene variante**, porque el censo de la bandeja lee el grupo de una literal.

⚠️ Exige VERDE antes de mutar y restaura cada fichero desde su copia en memoria.

Uso: python3 scripts/mutar-invitacion-t7-1.py
"""
import pathlib
import subprocess
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
EXEC = ['docker', 'compose', 'exec', '-T', '-u', 'sail', 'laravel.test']

MAIL = 'app/Notifications/GuestFormRequest.php'
TIPO = 'app/Domain/Booking/Models/TicketType.php'

T = 'GuestFormRequestInvitationTest::'


def php(test: str) -> list[str]:
    return EXEC + ['php', 'artisan', 'test', '--filter', test]


MUTATIONS = [
    (
        'con invitación, la llamada es COMPARTIR',
        MAIL,
        "                __($invita ? 'emails.guest_form.action_invite' : 'emails.guest_form.action'),",
        "                __('emails.guest_form.action'),",
        php(T + 'test_with_the_invitation_on_the_call_is_to_share_it'),
    ),
    (
        'sin invitación, el correo NO cambia (el control)',
        MAIL,
        '        $invita = $this->reservation->ticketType?->offersGuestInvitation() === true;',
        '        $invita = true;',
        php(T + 'test_without_the_invitation_the_email_does_not_change_a_word'),
    ),
    (
        'el cuerpo también cambia, no solo el botón',
        MAIL,
        "            ->line(__($invita ? 'emails.guest_form.intro_invite' : 'emails.guest_form.intro', ['product' => $product, 'code' => $code]))",
        "            ->line(__('emails.guest_form.intro', ['product' => $product, 'code' => $code]))",
        php(T + 'test_with_the_invitation_on_the_call_is_to_share_it'),
    ),
    (
        'el botón aterriza EN el bloque',
        MAIL,
        "                $invita ? $url.'#gf-invite' : $url,",
        '                $url,',
        php(T + 'test_the_button_lands_on_the_invitation_block_and_the_signature_survives'),
    ),
    (
        # ⚠️ Lo contrario de la anterior: el ancla NO puede colarse en el correo sin invitación,
        # donde no hay bloque al que bajar.
        'el ancla no viaja sin invitación',
        MAIL,
        "                $invita ? $url.'#gf-invite' : $url,",
        "                $url.'#gf-invite',",
        php(T + 'test_without_the_invitation_the_email_does_not_change_a_word'),
    ),
    (
        'el interruptor NO basta: hace falta columna de nombre',
        TIPO,
        "        return (bool) $this->guest_invitation\n            && $this->isPack()\n            && $this->guestNameFieldKey() !== null;",
        '        return (bool) $this->guest_invitation\n            && $this->isPack();',
        php(T + 'test_a_pack_without_a_name_column_is_not_an_invitation_pack'),
    ),
    (
        'el asunto no tiene variante',
        MAIL,
        "                ? __('emails.guest_form.subject', ['day' => $dia, 'code' => $code])",
        "                ? __($invita ? 'emails.guest_form.action_invite' : 'emails.guest_form.subject', ['day' => $dia, 'code' => $code])",
        php(T + 'test_the_inbox_line_has_no_variant'),
    ),
    (
        # ⚠️⚠️ **Un COMENTARIO puede romper el censo, y no es hipotético: pasó al escribir esta
        # unidad.** El escáner de `MailInboxLineTest` es un `grep` que se queda con la PRIMERA
        # llamada de cabecera del fichero, así que una nota que la escriba entre comillas para
        # explicarla le gana al código y el correo entero sale del censo. Es `#553` del lado del
        # inventario. La mutación deja demostrado que su guarda lo caza.
        'un comentario con la llamada literal saca el correo del censo',
        MAIL,
        '        // ⚠️⚠️ **El ASUNTO y la línea de adelanto NO cambian',
        "        // ejemplo que rompe el censo: ->hero('emails.inventado')\n        // ⚠️⚠️ **El ASUNTO y la línea de adelanto NO cambian",
        php('MailInboxLineTest::test_every_mail_has_its_inbox_pieces_in_the_three_languages'),
    ),
]


def green(cmd: list[str]) -> bool:
    return subprocess.run(cmd, cwd=ROOT, capture_output=True, text=True).returncode == 0


def clear_views() -> None:
    subprocess.run(EXEC + ['php', 'artisan', 'view:clear'], cwd=ROOT, capture_output=True, text=True)


def main() -> int:
    clear_views()
    for name, _path, _old, _new, cmd in MUTATIONS:
        if not green(cmd):
            print(f'CONTROL en ROJO antes de mutar («{name}»): el arnés no puede medir nada')
            return 2

    bitten = 0
    for name, path, old, new, cmd in MUTATIONS:
        file = ROOT / path
        original = file.read_text(encoding='utf-8')
        if original.count(old) != 1:
            print(f'la mutación «{name}» no encuentra su sitio EXACTO en {path}: el arnés ha caducado')
            return 2
        file.write_text(original.replace(old, new, 1), encoding='utf-8')
        clear_views()
        try:
            red = not green(cmd)
        finally:
            file.write_text(original, encoding='utf-8')
            clear_views()
        print(('MUERDE     ' if red else 'NO MUERDE  ') + name)
        bitten += int(red)

    print(f'{bitten}/{len(MUTATIONS)}')
    return 0 if bitten == len(MUTATIONS) else 1


if __name__ == '__main__':
    sys.exit(main())
