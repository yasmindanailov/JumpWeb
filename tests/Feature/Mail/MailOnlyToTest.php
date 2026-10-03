<?php

namespace Tests\Feature\Mail;

use App\Domain\Identity\Models\User;
use App\Notifications\LoginCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Message;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * **El BUZÓN TRAMPA** (`DECISIONES #883`, `RestrictMailRecipients`): con `mail.only_to`, el correo solo sale hacia esas
 * direcciones —staging envía de verdad, por el servidor de producción, sin escribir a nadie más—; vacío, como siempre.
 * Por el transporte de MEMORIA y no con `Mail::fake()`: el falso no dispara `MessageSending` y el filtro no se vería.
 */
class MailOnlyToTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.default' => 'array']);
    }

    public function test_without_a_list_mail_goes_to_everyone(): void
    {
        config(['mail.only_to' => []]);

        $this->enviar(['ana@example.com']);

        $this->assertSame([['ana@example.com']], $this->destinatarios(), 'vacío = sin límite (producción): el control');
    }

    public function test_with_a_list_only_the_listed_addresses_receive_it(): void
    {
        config(['mail.only_to' => ['owner@example.com']]);

        $this->enviar(['otra@example.com']);
        $this->enviar(['OWNER@example.com']);

        $this->assertSame([['OWNER@example.com']], $this->destinatarios(), 'el de fuera no sale; el de la lista sí, sin mirar mayúsculas');
    }

    public function test_a_mixed_message_keeps_only_the_listed_recipients(): void
    {
        config(['mail.only_to' => ['owner@example.com', 'equipo@example.com']]);

        $this->enviar(['owner@example.com', 'cliente@example.com'], cc: ['equipo@example.com', 'otro@example.com']);

        $mensaje = $this->transporte()->messages()->sole()->getOriginalMessage();
        $this->assertSame(['owner@example.com'], array_map(fn ($a) => $a->getAddress(), $mensaje->getTo()));
        $this->assertSame(['equipo@example.com'], array_map(fn ($a) => $a->getAddress(), $mensaje->getCc()));
    }

    /** El código para entrar —lo que se mide en staging— a una cuenta de fuera: ni sale ni queda como enviado. */
    public function test_a_login_code_to_an_address_outside_the_list_is_not_sent_nor_recorded(): void
    {
        config(['mail.only_to' => ['owner@example.com']]);
        $fuera = User::factory()->create(['email' => 'cliente@example.com']);
        $dentro = User::factory()->create(['email' => 'owner@example.com']);

        $fuera->notifyNow(new LoginCode('482913'));
        $dentro->notifyNow(new LoginCode('482913'));

        $this->assertSame([['owner@example.com']], $this->destinatarios());
        $this->assertSame(0, DB::table('email_sends')->where('user_id', $fuera->id)->count(), 'lo que no salió no es un envío');
    }

    /** @param  list<string>  $to  @param  list<string>  $cc */
    private function enviar(array $to, array $cc = []): void
    {
        Mail::raw('Hola', function (Message $m) use ($to, $cc): void {
            $m->to($to)->subject('Prueba');
            if ($cc !== []) {
                $m->cc($cc);
            }
        });
    }

    private function transporte(): ArrayTransport
    {
        /** @var ArrayTransport $t */
        $t = app('mail.manager')->mailer('array')->getSymfonyTransport();

        return $t;
    }

    /** @return list<list<string>> */
    private function destinatarios(): array
    {
        return $this->transporte()->messages()
            ->map(fn ($m) => array_map(fn ($a) => $a->getAddress(), $m->getOriginalMessage()->getTo()))
            ->values()->all();
    }
}
