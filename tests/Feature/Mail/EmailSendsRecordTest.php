<?php

namespace Tests\Feature\Mail;

use App\Domain\Identity\Models\User;
use App\Domain\Platform\Listeners\RecordEmailSend;
use App\Domain\Platform\Models\EmailSend;
use App\Notifications\AccountAlreadyExists;
use App\Notifications\Support\BrandedMailMessage;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Mail\SentMessage;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage as SymfonySentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Tests\Feature\Api\ApiTestCase;

/**
 * **Los correos que recibe el cliente, apuntados** (`specs/correos-salientes.md` §4.6, `DECISIONES #794`, la C1): una fila
 * por correo AL CLIENTE con la copia EXACTA de lo que salió; el reintento que acaba bien completa la MISMA fila; los avisos al
 * negocio no se apuntan; apuntar NUNCA rompe el envío; la supresión se lleva lo personal; la copia vive 6 meses y la fila 24.
 */
class EmailSendsRecordTest extends ApiTestCase
{
    public function test_a_customer_email_is_recorded_once_with_the_exact_copy_that_left(): void
    {
        $user = User::factory()->create(['email' => 'ana.correos@example.test', 'name' => 'Ana Correos']);

        $user->notify(new AccountAlreadyExists);

        $sent = $this->lastSentEmail();
        $row = EmailSend::query()->sole();

        $this->assertSame((int) $user->id, $row->user_id);
        $this->assertSame('account_already_exists', $row->mail_key);
        $this->assertSame('ana.correos@example.test', $row->recipient);
        $this->assertSame($sent->getSubject(), $row->subject);
        $this->assertSame((string) $sent->getHtmlBody(), $row->html, 'la copia es EXACTAMENTE lo que salió');
        $this->assertNotNull($row->sent_at);
        $this->assertSame(0, $row->failures);
        $this->assertSame($sent->getHeaders()->get(EmailSend::HEADER)?->getBodyAsString(), $row->send_key, 'la clave del envío viaja en el correo');
    }

    public function test_someone_without_an_account_is_recorded_by_address(): void
    {
        NotificationFacade::route('mail', 'padre.sin.cuenta@example.test')->notify(new AccountAlreadyExists);

        $row = EmailSend::query()->sole();
        $this->assertNull($row->user_id);
        $this->assertSame('padre.sin.cuenta@example.test', $row->recipient);
    }

    /** Un correo que no va al cliente (el equipo, el negocio) no se apunta: no es de nadie a quien enseñárselo. */
    public function test_a_mail_that_is_not_to_a_customer_is_not_recorded(): void
    {
        User::factory()->create()->notify(new AvisoInternoDePrueba);

        $this->assertNotNull($this->lastSentEmail(), 'el correo salió');
        $this->assertSame(0, EmailSend::query()->count());
    }

    /** Un `toMail()` llamado a mano (una vista previa, una prueba) no es un envío: sin id del framework, sin marca. */
    public function test_a_to_mail_called_by_hand_carries_no_send_mark(): void
    {
        $message = (new AccountAlreadyExists)->toMail(User::factory()->create());
        $email = new Email;
        foreach ($message->callbacks as $callback) {
            $callback($email);
        }

        $this->assertInstanceOf(BrandedMailMessage::class, $message);
        $this->assertNull($email->getHeaders()->get(EmailSend::HEADER));
    }

    /** Falla, la cola reintenta y sale: UNA fila, con los fallos contados. Y si nunca sale, queda «no salió». */
    public function test_a_failure_then_a_retry_completes_the_same_row(): void
    {
        $user = User::factory()->create(['email' => 'reintento@example.test']);
        $notification = new AccountAlreadyExists;
        $notification->id = '0c9a7a3e-1111-4a4a-9b9b-222233334444';
        $listener = app(RecordEmailSend::class);

        $listener->failed(new NotificationFailed($user, $notification, 'mail', ['exception' => new \RuntimeException('smtp caído')]));
        $listener->failed(new NotificationFailed($user, $notification, 'mail', ['exception' => new \RuntimeException('smtp caído')]));

        $row = EmailSend::query()->sole();
        $this->assertNull($row->sent_at);
        $this->assertSame(2, $row->failures);
        $this->assertSame('reintento@example.test', $row->recipient);

        $listener->sent(new NotificationSent($user, $notification, 'mail', $this->sentMessage($notification->id, 'reintento@example.test')));

        $row = EmailSend::query()->sole();
        $this->assertNotNull($row->sent_at, 'el reintento que sale bien completa la MISMA fila');
        $this->assertSame(2, $row->failures);
        $this->assertSame('<p>Hola</p>', $row->html);
    }

    /**
     * ⚠️⚠️ **Apuntar NUNCA rompe el envío** (`#794`): sin la tabla, el correo sale igual y el oyente solo deja una línea en el
     * registro. Si la excepción subiera, el trabajo de la cola se reintentaría y el cliente recibiría el correo DOS veces.
     */
    public function test_a_broken_record_never_breaks_the_send(): void
    {
        Schema::drop('email_sends');
        Log::spy();

        User::factory()->create()->notify(new AccountAlreadyExists);

        $this->assertNotNull($this->lastSentEmail(), 'el correo salió aunque apuntarlo fallara');
        Log::shouldHaveReceived('warning')->withArgs(static fn (string $message): bool => $message === 'email_sends.record_failed')->once();
    }

    /**
     * ⚠️⚠️ **Fuera de la transacción de quien envía** (`#794`, como `Recorder`): con la cola `sync` el correo sale DENTRO de la
     * transacción de quien lo dispara (un pedido, un pago). Un deadlock al apuntar desharía en InnoDB la transacción ENTERA y,
     * tragado el error, el código de fuera seguiría sin saberlo. Se apunta tras su commit, fuera de su lock.
     */
    public function test_inside_someone_elses_transaction_the_record_waits_for_its_commit(): void
    {
        $user = User::factory()->create();

        DB::transaction(function () use ($user): void {
            $user->notify(new AccountAlreadyExists);

            $this->assertNotNull($this->lastSentEmail(), 'el correo salió en el acto (cola sync)');
            $this->assertSame(0, EmailSend::query()->count(), 'dentro de la transacción de otro no se escribe nada');
        });

        $this->assertSame(1, EmailSend::query()->count(), 'tras su commit, la fila');
    }

    // ─── Los plazos y la supresión ───────────────────────────────────────────────────────────────

    public function test_the_copy_goes_at_six_months_and_the_row_at_twenty_four(): void
    {
        $vieja = $this->row(now()->subMonths(7));
        $nueva = $this->row(now()->subMonths(5));
        $antigua = $this->row(now()->subMonths(25));

        $this->artisan('email-sends:trim')->assertSuccessful();

        $this->assertNull($vieja->fresh()->html);
        $this->assertNull($vieja->fresh()->subject);
        $this->assertNotNull($vieja->fresh()->copy_purged_at);
        $this->assertNotNull($vieja->fresh()->sent_at, 'la fila y sus cifras se quedan');
        $this->assertSame('<p>Copia</p>', $nueva->fresh()->html, 'la de hace cinco meses conserva su copia');

        $this->artisan('model:prune', ['--model' => [EmailSend::class]])->assertSuccessful();

        $this->assertNull($antigua->fresh(), 'a los 24 meses se va la fila');
        $this->assertNotNull($vieja->fresh());
    }

    /** Los dos plazos corren SOLOS: sin la tarea en el planificador, la copia viviría para siempre y nada fallaría. */
    public function test_both_retentions_run_every_night_from_the_scheduler(): void
    {
        $events = collect(app(Schedule::class)->events());

        // ⚠️ El nombre EXACTO al final de la orden, no una subcadena: `email-sends:trim-no` contiene `email-sends:trim` y
        // pasaba (lo cazó el arnés). Y que esa orden exista de verdad.
        $trim = $events->filter(static fn ($event): bool => preg_match('/(^|\s)email-sends:trim$/', trim((string) $event->command)) === 1);
        $this->assertCount(1, $trim);
        $this->assertArrayHasKey('email-sends:trim', Artisan::all());
        $this->assertSame('40 4 * * *', $trim->first()->expression);

        $prune = $events->filter(static fn ($event): bool => str_contains((string) $event->command, 'model:prune'));
        $this->assertStringContainsString('EmailSend', (string) $prune->first()?->command, 'la poda de 24 meses incluye los correos');
    }

    /** `RGPD-01`: la supresión se lleva la copia, el asunto, la dirección y a él; las cifras se quedan, sin nadie. */
    public function test_anonymize_forgets_the_person_in_every_send_and_keeps_the_figures(): void
    {
        $user = User::factory()->create();
        $suyo = $this->row(now(), $user->id);
        $ajeno = $this->row(now(), User::factory()->create()->id);

        $this->assertTrue($user->anonymize());

        $suyo = $suyo->fresh();
        $this->assertNull($suyo->user_id);
        $this->assertNull($suyo->recipient);
        $this->assertNull($suyo->subject);
        $this->assertNull($suyo->html);
        $this->assertNotNull($suyo->sent_at);
        $this->assertSame('<p>Copia</p>', $ajeno->fresh()->html, 'el correo de OTRA persona no se toca (el control)');
    }

    /** El export del art. 20 lleva sus envíos (sin la copia), con el contrato. */
    public function test_the_export_carries_the_sends_without_the_copy(): void
    {
        $user = User::factory()->create();
        $this->row(now()->subDay(), $user->id);

        $this->actingAs($user)->getJson(self::ROOT.'/me/export')
            ->assertOk()->assertValidResponse(200)
            ->assertJsonPath('emails.0.mail', 'account_already_exists')
            ->assertJsonPath('emails.0.subject', 'Asunto')
            ->assertJsonPath('emails.0.failures', 0)
            ->assertJsonMissingPath('emails.0.html');
    }

    // ─── Andamios ────────────────────────────────────────────────────────────────────────────────

    private function lastSentEmail(): ?Email
    {
        /** @var TransportInterface&ArrayTransport $transport */
        $transport = app('mailer')->getSymfonyTransport();
        $last = $transport->messages()->last();
        $email = $last?->getOriginalMessage();

        return $email instanceof Email ? $email : null;
    }

    private function sentMessage(string $send, string $to): SentMessage
    {
        $email = (new Email)->from('parque@example.test')->to($to)->subject('Ya tienes cuenta')->html('<p>Hola</p>');
        $email->getHeaders()->addTextHeader(EmailSend::HEADER, $send);

        return new SentMessage(new SymfonySentMessage($email, new Envelope(new Address('parque@example.test'), [new Address($to)])));
    }

    private function row(\DateTimeInterface $at, ?int $userId = null): EmailSend
    {
        $id = DB::table('email_sends')->insertGetId([
            'send_key' => (string) Str::uuid(), 'user_id' => $userId, 'recipient' => 'alguien@example.test',
            'mail_key' => 'account_already_exists', 'subject' => 'Asunto', 'html' => '<p>Copia</p>', 'attachments' => '[]',
            'sent_at' => $at, 'created_at' => $at, 'updated_at' => $at,
        ]);

        return EmailSend::query()->findOrFail($id);
    }
}

/** Un correo que NO es al cliente: su clase no es una de las claves de `EmailUtm`. */
class AvisoInternoDePrueba extends Notification
{
    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): BrandedMailMessage
    {
        return (new BrandedMailMessage($this))->subject('Aviso interno')->line('Solo para el equipo.');
    }
}
