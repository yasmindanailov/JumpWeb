#!/usr/bin/env python3
"""Arnés de mutación de la T5·5 — el flujo firmar ↔ invitación (`docs/specs/celebracion-e-invitacion.md` §10.6, `#704`).

Cada mutación rompe UNA propiedad de la tanda y comprueba que su guarda se pone en rojo:

  · el recibo deja de ofrecer firmar a quien ya firmó (§10.6·C), y lo pregunta POR EL CONTRATO;
  · quien firma desde su invitación vuelve a SU recibo (§10.6·D);
  · el recibo —credencial de dos horas sobre los datos de un menor— solo se emite desde la FIRMA de la
    URL y solo para una respuesta DE ESTA reserva;
  · un formulario rechazado vuelve con la atadura puesta (si no, el segundo intento cobra plaza).

⚠️ Exige VERDE antes de mutar (un filtro que no casa con ningún caso sale ≠ 0 y parecería que muerde) y
restaura cada fichero desde su copia en memoria —NUNCA con `git checkout`, que se llevaría el trabajo sin
commitear (la trampa pagada en `#298`)—.

Uso: python3 scripts/mutar-flujo-invitacion.py
"""
import pathlib
import subprocess
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
EXEC = ['docker', 'compose', 'exec', '-T', '-u', 'sail', 'laravel.test']

CONTROLADOR = 'app/Http/Controllers/GuardianAuthorizationController.php'
INVITACIONES = 'app/Domain/Booking/Services/PartyInvitations.php'
PLAZAS = 'app/Domain/Identity/Services/GuardianPlaces.php'
RECIBO = 'resources/views/invitation/receipt.blade.php'


def php(test: str) -> list[str]:
    return EXEC + ['php', 'artisan', 'test', '--filter', test]


YA_FIRMADO = php('InvitationSigningFlowTest::test_a_reply_that_already_has_a_waiver_is_not_offered_to_sign_again')
VUELVE = php('InvitationSigningFlowTest::test_after_signing_from_the_invitation_the_parent_lands_back_on_his_receipt')
ATADURA = php('InvitationSigningFlowTest::test_a_rejected_form_comes_back_with_the_invitation_still_tied')
SIN_FIRMA = php('InvitationSigningFlowTest::test_without_a_signed_url_no_receipt_is_minted')
AJENA = php('InvitationSigningFlowTest::test_a_reply_from_another_party_gets_no_receipt')
CONTRATO = php('ModuleContractsTest::test_booking_asks_identity_whether_a_reply_is_already_signed')

MUTATIONS = [
    (
        'el recibo esconde el botón cuando ya está firmado',
        RECIBO,
        '@if ($signed)',
        '@if (false)',
        YA_FIRMADO,
    ),
    (
        'la atadura es lo que dice si está firmado',
        PLAZAS,
        "return GuardianAuthorization::query()->where('invitation_reply_id', $replyId)->exists();",
        'return false;',
        YA_FIRMADO,
    ),
    (
        'Booking lo pregunta por el CONTRATO, no mirando',
        INVITACIONES,
        'return $this->signed->isReplySigned((int) $reply->getKey());',
        'return false;',
        CONTRATO,
    ),
    (
        'tras firmar se vuelve al recibo',
        CONTROLADOR,
        '        if ($recibo !== null) {',
        '        if (false) {',
        VUELVE,
    ),
    (
        'el recibo solo se emite desde la FIRMA de la URL',
        CONTROLADOR,
        '        if (! $request->hasValidSignature()) {\n            return null;\n        }',
        '        if (false) {\n            return null;\n        }',
        SIN_FIRMA,
    ),
    (
        'el recibo es de una respuesta DE ESTA reserva',
        INVITACIONES,
        "            ->where('order_item_id', $reservationId)\n",
        '',
        AJENA,
    ),
    (
        'la vuelta del formulario conserva la atadura',
        CONTROLADOR,
        'return $reservation->guardianAuthorizationSignedUrl($extras);',
        'return $reservation->guardianAuthorizationSignedUrl();',
        ATADURA,
    ),
]


def green(cmd: list[str]) -> bool:
    return subprocess.run(cmd, cwd=ROOT, capture_output=True, text=True).returncode == 0


def main() -> int:
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
        try:
            red = not green(cmd)
        finally:
            file.write_text(original, encoding='utf-8')
        print(('MUERDE     ' if red else 'NO MUERDE  ') + name)
        bitten += int(red)

    print(f'{bitten}/{len(MUTATIONS)}')
    return 0 if bitten == len(MUTATIONS) else 1


if __name__ == '__main__':
    sys.exit(main())
