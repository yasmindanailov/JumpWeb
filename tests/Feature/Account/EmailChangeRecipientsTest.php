<?php

namespace Tests\Feature\Account;

use App\Domain\Identity\Models\LoginCode;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\LoginCodes;
use Illuminate\Mail\Transport\ArrayTransport;
use Symfony\Component\Mime\Email;
use Tests\Feature\Api\ApiTestCase;

/**
 * A2b de `docs/specs/acceso-con-codigo.md` (`DECISIONES #856`) — **a qué BUZÓN salen de verdad los dos correos del cambio de
 * correo**, con el transporte de la suite y sin `Notification::fake()`.
 *
 * ⚠️⚠️ Medido el 30-09: los dos salían al buzón equivocado desde siempre. El de «confirma tu nuevo email» llegaba al VIEJO
 * —el cambio se confirmaba sin probar el nuevo— y el aviso de que se hizo, al NUEVO —la víctima de un robo no se enteraba—.
 * Declaraban su destinatario en la notificación, y Laravel solo lee el del titular. Ninguna prueba lo veía: con
 * `fake()` no hay dirección, y la que la miraba llamaba a mano al método que el framework no usa.
 *
 * El aviso al viejo va además por la COLA (`sync` en la suite, que serializa igual): al volver de ella el titular ya tiene
 * el correo nuevo, y el viejo tiene que viajar dentro de la notificación.
 */
class EmailChangeRecipientsTest extends ApiTestCase
{
    private function holder(): User
    {
        return User::factory()->create(['email' => 'ana.vieja@example.test', 'email_verified_at' => now()]);
    }

    /** @return list<Email> */
    private function sent(): array
    {
        /** @var ArrayTransport $transport */
        $transport = app('mailer')->getSymfonyTransport();

        return array_values(array_filter(
            array_map(static fn ($m) => $m->getOriginalMessage(), $transport->messages()->all()),
            static fn ($m): bool => $m instanceof Email,
        ));
    }

    /** @return list<string> los destinatarios de los correos enviados cuyo asunto contiene `$needle` */
    private function recipientsOf(string $needle): array
    {
        $out = [];
        foreach ($this->sent() as $mail) {
            if (str_contains((string) $mail->getSubject(), $needle)) {
                $out[] = $mail->getTo()[0]->getAddress();
            }
        }

        return $out;
    }

    public function test_the_new_email_code_goes_to_the_new_mailbox(): void
    {
        $user = $this->holder();
        $code = app(LoginCodes::class)->issue('ana.vieja@example.test', LoginCode::PURPOSE_CONFIRM, '203.0.113.60');

        $this->actingAs($user)->patchJson(self::ROOT.'/me', [
            'name' => $user->name, 'phone' => (string) $user->phone, 'locale' => 'es',
            'email' => 'ana.nueva@example.test', 'code' => $code,
        ])->assertOk();

        $this->assertSame(['ana.nueva@example.test'], $this->recipientsOf('código de tu nuevo email'));
        // Y el aviso de que se ha PEDIDO, al viejo (este ya iba bien: usa el correo de la cuenta).
        $this->assertSame(['ana.vieja@example.test'], $this->recipientsOf(__('emails.email_change_requested.subject')));
    }

    public function test_the_notice_that_it_changed_goes_to_the_old_mailbox_even_through_the_queue(): void
    {
        $user = $this->holder();
        $user->forceFill(['pending_email' => 'ana.nueva@example.test', 'pending_email_sent_at' => now()])->save();
        $code = app(LoginCodes::class)->issue('ana.nueva@example.test', LoginCode::PURPOSE_NEW_EMAIL, '203.0.113.60');

        $this->actingAs($user)->postJson(self::ROOT.'/me/pending-email/confirm', ['code' => $code])->assertOk();

        $this->assertSame(['ana.vieja@example.test'], $this->recipientsOf(__('emails.email_change_completed.subject')));
    }
}
