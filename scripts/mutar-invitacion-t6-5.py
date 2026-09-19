#!/usr/bin/env python3
"""Arnés de mutación de la T6·5: la invitación en la ficha del pedido
(`docs/specs/celebracion-e-invitacion.md` §4.8 y §10.12; `DECISIONES #712`).

Cada mutación rompe UNA propiedad de la tanda y comprueba que su guarda se pone en rojo:

  · el panel reparte el enlace **PÚBLICO** de la fiesta, no el del formulario;
  · el panel **no materializa** la invitación: nace en el GET del anfitrión;
  · sin nombre de quien cumple **no hay enlace** que copiar;
  · anular **rota el token** y **no borra** lo contestado;
  · y sin el permiso **no se anula**, con su rastro.

⚠️ Exige VERDE antes de mutar y restaura cada fichero desde su copia en memoria.

Uso: python3 scripts/mutar-invitacion-t6-5.py
"""
import pathlib
import subprocess
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
EXEC = ['docker', 'compose', 'exec', '-T', '-u', 'sail', 'laravel.test']

PAGE = 'app/Filament/Resources/Orders/Pages/ViewOrder.php'
BLADE = 'resources/views/filament/orders/items-list.blade.php'


def php(test: str) -> list[str]:
    return EXEC + ['php', 'artisan', 'test', '--filter', test]


MUTATIONS = [
    (
        'el panel NO materializa la invitación',
        PAGE,
        '        $invitation = $invitations->existingFor($item);',
        '        $invitation = $invitations->forReservation($item);',
        php('InvitationPanelTest::test_the_panel_never_materialises_the_invitation'),
    ),
    (
        'sin nombre de quien cumple no hay enlace',
        PAGE,
        '        if ($invitation === null || ! $invitations->isShareable($item, $invitation)) {',
        '        if ($invitation === null) {',
        php('InvitationPanelTest::test_without_a_celebrant_name_there_is_no_link_to_copy'),
    ),
    (
        'el enlace es el PÚBLICO de la fiesta',
        PAGE,
        '        return $invitations->shareUrlFor($invitation);',
        '        return $item->guestFormSignedUrl();',
        php('InvitationPanelTest::test_the_panel_offers_the_public_link_of_the_party'),
    ),
    (
        # ⚠️ La ACCIÓN de anular es de `#576` y ya tiene sus casos (`InvitationLinkRotationTest`); lo
        # que esta tanda añade es su BOTÓN, y es lo que se muta aquí.
        'el botón de ANULAR se pinta',
        BLADE,
        '                            wire:click="mountAction(\'rotateInvitationLink\', { item: {{ $item->id }} })"',
        '                            wire:click="mountAction(\'noExiste\', { item: {{ $item->id }} })"',
        php('InvitationPanelTest::test_the_line_paints_both_buttons_once_the_invitation_exists'),
    ),
    (
        'sin el permiso no se pinta el botón de anular',
        BLADE,
        "                    @if (auth()->user()?->hasPermission('orders.edit_guest_data'))\n                        <x-filament::icon-button\n                            wire:click=\"mountAction('rotateInvitationLink', { item: {{ $item->id }} })\"",
        "                    @if (true)\n                        <x-filament::icon-button\n                            wire:click=\"mountAction('rotateInvitationLink', { item: {{ $item->id }} })\"",
        php('InvitationPanelTest::test_an_operator_without_the_permission_does_not_even_see_the_void_button'),
    ),
    (
        'el resumen de la línea dice lo que hay',
        PAGE,
        '                : $invitations->summaryFor($item);',
        "                : ['yes' => 0, 'no' => 0, 'pending' => 0];",
        php('InvitationPanelTest::test_the_line_says_how_the_replies_are_going'),
    ),
    (
        # ⚠️ Lo que se vigila es el IDOR, no el «pagado»: aquello lo niega el dominio, y su copia en
        # esta página se retiró porque ninguna prueba podía tumbarla (`#704`).
        'el enlace de OTRO pedido no se acuña',
        PAGE,
        '        if ((int) $item->order_id !== (int) $order->id) {\n            return null;\n        }\n',
        '',
        php('InvitationPanelTest::test_the_link_of_another_order_is_never_minted'),
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
