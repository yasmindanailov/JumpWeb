<?php

namespace Tests\Support;

use App\Domain\Booking\Models\InvitationReply;
use App\Domain\Booking\Models\PartyInvitation;
use App\Domain\Booking\Services\PartyInvitations;
use App\Domain\Identity\Models\BirthdayReminder;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Platform\Services\DisplayTime;

/**
 * **«Avísame de fechas», pedido como lo pide un padre** (`specs/avisame-de-fechas.md` §4.2): una fiesta, un «sí» desde la
 * invitación, la autorización firmada desde su recibo con ese correo y ese nacimiento, y la casilla marcada. Lo comparten la
 * tanda A2 (`AvisameDeFechasEnvioTest`) y el 12 ampliado de la C1a (`CumpleSeAcercaPorNovedadesTest`). La clase pone la
 * fiesta (`MountsAParty`).
 */
trait SignsABirthdayReminder
{
    /** @return array<string, mixed> */
    abstract protected function mountParty(): array;

    /** Una fecha de nacimiento cuyo PRÓXIMO cumpleaños cae dentro de `$dias` días, el de `$cumple` años. */
    protected function nacidoConCumpleEn(int $dias, int $cumple = 8): string
    {
        return DisplayTime::today()->addDays($dias)->subYears($cumple)->toDateString();
    }

    /**
     * El camino entero: `$nombre`, el nombre si es compuesto (si no, la primera palabra de `$nino`).
     */
    protected function pedirAviso(string $nino, string $correo, string $nacio, ?string $nombre = null): BirthdayReminder
    {
        ['invitation' => $invitation] = $this->mountParty();
        /** @var PartyInvitation $invitation */
        $this->post(route('invitation.reply', ['token' => $invitation->token]), ['child_name' => $nino, 'attending' => '1'])->assertRedirect();
        $reply = InvitationReply::query()->where('party_invitation_id', $invitation->getKey())->latest('id')->firstOrFail();
        $url = app(PartyInvitations::class)->receiptUrl($reply);
        $this->assertSame(1, preg_match('#<form method="post" action="([^"]+)"[^>]*data-receipt-firma#', (string) $this->get($url)->getContent(), $m));
        $nombre ??= explode(' ', $nino, 2)[0];
        $apellido = trim(substr($nino, strlen($nombre)));
        $this->post(html_entity_decode($m[1]), [
            'document_id' => LegalDocumentVersion::query()->orderByDesc('id')->firstOrFail()->getKey(),
            'accept_waiver' => '1', 'invitation_reply_id' => (string) $reply->getKey(),
            'minor_name' => $nombre, 'minor_surname' => $apellido, 'minor_born_on' => $nacio,
            'guardian_name' => 'Marta Ruiz', 'guardian_relationship' => 'mother', 'guardian_phone' => '600111222',
            'guardian_email' => $correo,
        ])->assertRedirect();
        $this->postJson($url, ['dates' => '1'])->assertOk()->assertJson(['saved' => true]);

        return BirthdayReminder::query()->latest('id')->firstOrFail();
    }
}
