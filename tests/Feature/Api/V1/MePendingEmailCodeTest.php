<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Identity\Models\LoginCode;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Identity\Services\AccountProfile;
use App\Domain\Identity\Services\EmailCodeLogin;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\LoginCodes;
use App\Domain\Platform\Models\EmailSend;
use App\Domain\Platform\Models\Setting;
use App\Notifications\EmailChangeCompleted;
use App\Notifications\VerifyPendingEmail;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Api\ApiTestCase;

/**
 * A2b de `docs/specs/acceso-con-codigo.md` (§4.9, `DECISIONES #856`) — **el correo NUEVO, confirmado con un código enviado a
 * ESE buzón** (`POST /me/pending-email/confirm`): la prueba de que el buzón es de quien pidió el cambio, escrita en el mismo
 * dispositivo. Desde la A5 (`#869`) es la única: el enlace firmado se retiró, y con él `EmailChangeConfirmTest`, cuyo único
 * caso que el código no cubría —el descargo pendiente que se firma al verificar— vive ahora aquí.
 *
 * Lo que se prueba: que pedir el cambio y reenviarlo emitan un código al buzón nuevo, tras la respuesta y no por la cola;
 * que con él el cambio se complete —verificado, con el aviso al viejo y el descargo pendiente firmado— y con uno malo no
 * pase NADA; que un código de otro propósito no valga; los desenlaces (caducada, tomada, nada pendiente); el limitador; y
 * que la copia no guarde el código. Arnés: `scripts/mutar-acceso-codigo.sh`.
 */
class MePendingEmailCodeTest extends ApiTestCase
{
    private const OLD = 'ana.antes@example.test';

    private const NEW = 'ana.despues@example.test';

    private const IP = '203.0.113.70';

    private function holder(): User
    {
        return User::factory()->create(['email' => self::OLD, 'email_verified_at' => now()]);
    }

    /** Un titular con el cambio YA pedido y un código vivo para el buzón nuevo. */
    private function pending(User $user): string
    {
        $user->forceFill(['pending_email' => self::NEW, 'pending_email_sent_at' => now()])->save();

        return app(LoginCodes::class)->issue(self::NEW, LoginCode::PURPOSE_NEW_EMAIL, self::IP);
    }

    private function wrong(string $code): string
    {
        return str_pad((string) (((int) $code + 1) % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    /** El código que llevó el último correo al buzón nuevo, leído del asunto como lo lee el cliente. */
    private function codeSentTo(User $user): string
    {
        $sent = Notification::sent($user, VerifyPendingEmail::class)->last();
        $this->assertInstanceOf(VerifyPendingEmail::class, $sent);
        preg_match('/(\d{3}) (\d{3})/', (string) $sent->toMail($user->fresh())->subject, $m);

        return $m[1].$m[2];
    }

    private function requestChange(User $user): void
    {
        $confirm = app(LoginCodes::class)->issue(self::OLD, LoginCode::PURPOSE_CONFIRM, self::IP);

        $this->actingAs($user)->patchJson(self::ROOT.'/me', [
            'name' => $user->name, 'phone' => (string) $user->phone, 'locale' => 'es', 'email' => self::NEW, 'code' => $confirm,
        ])->assertOk();
    }

    public function test_asking_for_the_change_sends_a_code_to_the_new_mailbox_and_it_completes_the_change(): void
    {
        Notification::fake();
        $user = $this->holder();
        $this->requestChange($user);

        $this->actingAs($user)
            ->postJson(self::ROOT.'/me/pending-email/confirm', ['code' => $this->codeSentTo($user)])
            ->assertOk()
            ->assertValidRequest()
            ->assertValidResponse(200)
            ->assertJsonPath('email', self::NEW);

        $fresh = $user->fresh();
        $this->assertSame(self::NEW, $fresh->email);
        $this->assertNull($fresh->pending_email);
        $this->assertTrue($fresh->hasVerifiedEmail());
        Notification::assertSentTo($user, EmailChangeCompleted::class);
    }

    /**
     * S-5 (`#181`): confirmar el correo NUEVO es verificarlo — la aceptación pendiente del descargo se firma. Era del enlace
     * (`EmailChangeConfirmTest`, retirado en la A5); la regla vive en el dominio (`completeEmailChange`) y llega por el código.
     */
    public function test_confirming_the_new_email_signs_a_pending_waiver_acceptance(): void
    {
        Setting::updateOrCreate(['key' => 'waiver.mode'], ['value' => 'interno', 'group' => 'waiver']);
        $document = app(LegalDocumentPublisher::class)->publish('waiver', [
            'es' => ['title' => 'Exención', 'body' => [['h' => 'Riesgo', 'p' => 'Acepto el riesgo.']]],
        ])->first();
        $user = User::factory()->create(['email' => self::OLD, 'email_verified_at' => null]);
        $user->forceFill(['waiver_pending_document_id' => $document->id, 'waiver_pending_channel' => 'web'])->save();
        $code = $this->pending($user);

        $this->actingAs($user)->postJson(self::ROOT.'/me/pending-email/confirm', ['code' => $code])->assertOk();

        $this->assertSame(1, WaiverSignature::where('user_id', $user->id)->count());
        $this->assertNull($user->fresh()->waiver_pending_document_id);
    }

    /** El código sale tras la respuesta, no por la cola: quien pidió el cambio lo está esperando en la pantalla. */
    public function test_the_code_mail_does_not_wait_for_the_queue(): void
    {
        Queue::fake();
        $user = $this->holder();

        $this->requestChange($user);

        Queue::assertNotPushed(SendQueuedNotifications::class, static fn ($job): bool => $job->notification instanceof VerifyPendingEmail);
    }

    /** ⚠️⚠️ Con el código malo NO cambia nada, y el 422 va sobre `code`. */
    public function test_a_wrong_code_changes_nothing(): void
    {
        $user = $this->holder();
        $code = $this->pending($user);

        $this->actingAs($user)
            ->postJson(self::ROOT.'/me/pending-email/confirm', ['code' => $this->wrong($code)])
            ->assertStatus(422)
            ->assertValidResponse(422)
            ->assertJsonPath('error.fields.code.0', __('api.new_email.wrong_code'));

        $this->assertSame(self::OLD, $user->fresh()->email);
        $this->assertSame(self::NEW, $user->fresh()->pending_email);
    }

    /** Un código al buzón nuevo para OTRA cosa (entrar, confirmar) no completa el cambio: el propósito va en la huella. */
    public function test_a_code_for_another_purpose_does_not_complete_the_change(): void
    {
        $user = $this->holder();
        $this->pending($user);
        $loginCode = app(LoginCodes::class)->issue(self::NEW, LoginCode::PURPOSE_LOGIN, self::IP);

        $this->actingAs($user)->postJson(self::ROOT.'/me/pending-email/confirm', ['code' => $loginCode])->assertStatus(422);

        $this->assertSame(self::OLD, $user->fresh()->email);
    }

    public function test_an_expired_request_is_cleared_and_said(): void
    {
        $user = $this->holder();
        $code = $this->pending($user);
        $user->forceFill(['pending_email_sent_at' => now()->subMinutes(AccountProfile::PENDING_EMAIL_HOLD_MINUTES + 1)])->save();

        $this->actingAs($user)
            ->postJson(self::ROOT.'/me/pending-email/confirm', ['code' => $code])
            ->assertStatus(422)
            ->assertJsonPath('error.fields.code.0', __('api.new_email.expired'));

        $this->assertNull($user->fresh()->pending_email);
        $this->assertSame(self::OLD, $user->fresh()->email);
    }

    public function test_an_email_taken_in_between_is_cleared_and_said_on_the_email_field(): void
    {
        $user = $this->holder();
        $code = $this->pending($user);
        User::factory()->create(['email' => self::NEW]);

        $this->actingAs($user)
            ->postJson(self::ROOT.'/me/pending-email/confirm', ['code' => $code])
            ->assertStatus(422)
            ->assertJsonMissingPath('error.fields.code')
            ->assertJsonPath('error.fields.email.0', __('validation.unique', ['attribute' => __('account.account.profile.email')]));

        $this->assertNull($user->fresh()->pending_email);
    }

    public function test_nothing_pending_is_said(): void
    {
        $this->actingAs($this->holder())
            ->postJson(self::ROOT.'/me/pending-email/confirm', ['code' => '123456'])
            ->assertStatus(422)
            ->assertJsonPath('error.fields.code.0', __('api.new_email.nothing_pending'));
    }

    /** Cinco fallos por (titular, IP) y el código BUENO ya no completa hasta que pase el minuto. */
    public function test_five_wrong_codes_lock_the_confirmation(): void
    {
        $user = $this->holder();
        $user->forceFill(['pending_email' => self::NEW, 'pending_email_sent_at' => now()])->save();
        $codes = app(LoginCodes::class);

        // Un código nuevo antes de cada fallo: cada uno muere a los cinco intentos, y aquí se mide el limitador, no eso.
        foreach (range(1, AccountProfile::MAX_CONFIRM_ATTEMPTS) as $ignored) {
            $code = $codes->issue(self::NEW, LoginCode::PURPOSE_NEW_EMAIL, self::IP);
            $this->actingAs($user)->postJson(self::ROOT.'/me/pending-email/confirm', ['code' => $this->wrong($code)])->assertStatus(422);
        }
        $good = $codes->issue(self::NEW, LoginCode::PURPOSE_NEW_EMAIL, self::IP);

        $this->actingAs($user)->postJson(self::ROOT.'/me/pending-email/confirm', ['code' => $good])
            ->assertStatus(429)
            ->assertValidResponse(429);

        $this->assertSame(self::OLD, $user->fresh()->email);
    }

    public function test_resending_sends_a_new_code_and_kills_the_previous_one(): void
    {
        Notification::fake();
        $user = $this->holder();
        $this->requestChange($user);
        $first = $this->codeSentTo($user);

        $this->travel(61)->seconds();
        $this->actingAs($user)->postJson(self::ROOT.'/me/pending-email/resend')->assertNoContent();
        $second = $this->codeSentTo($user);

        if ($first === $second) {
            $this->markTestSkipped('los dos códigos salieron iguales (una vez en un millón)');
        }

        $this->actingAs($user)->postJson(self::ROOT.'/me/pending-email/confirm', ['code' => $first])->assertStatus(422);
        $this->actingAs($user)->postJson(self::ROOT.'/me/pending-email/confirm', ['code' => $second])->assertOk();
    }

    /** Cada reenvío es un código al buzón que eligió quien pide el cambio —puede ser de otro—: cinco por hora. */
    public function test_five_resends_an_hour(): void
    {
        Notification::fake();
        $user = $this->holder();
        $user->forceFill(['pending_email' => self::NEW, 'pending_email_sent_at' => now()])->save();

        foreach (range(1, EmailCodeLogin::MAX_PER_EMAIL_PER_HOUR) as $ignored) {
            $this->actingAs($user)->postJson(self::ROOT.'/me/pending-email/resend')->assertNoContent();
            $this->travel(61)->seconds();
        }

        $limit = $this->actingAs($user)->postJson(self::ROOT.'/me/pending-email/resend')->assertStatus(429);
        $this->assertGreaterThan(60, (int) $limit->headers->get('Retry-After'), 'con los cinco de la hora gastados, la espera es la de la hora');
        Notification::assertSentToTimes($user, VerifyPendingEmail::class, EmailCodeLogin::MAX_PER_EMAIL_PER_HOUR);
    }

    /**
     * ⚠️ Y reenviar dos veces seguidas dice la espera de SU ventana, no la de la hora, que corre con 1 de 5 y decía 3.599 s
     * (`EmailCodeLogin::secondsToWait`, medido el 01-10).
     */
    public function test_resending_twice_in_a_row_says_the_short_wait_not_the_hour(): void
    {
        Notification::fake();
        $user = $this->holder();
        $user->forceFill(['pending_email' => self::NEW, 'pending_email_sent_at' => now()])->save();

        $this->actingAs($user)->postJson(self::ROOT.'/me/pending-email/resend')->assertNoContent();
        $limit = $this->actingAs($user)->postJson(self::ROOT.'/me/pending-email/resend')->assertStatus(429)->assertHeader('Retry-After');

        $wait = (int) $limit->headers->get('Retry-After');
        $this->assertGreaterThan(0, $wait);
        $this->assertLessThanOrEqual(60, $wait, "reenviar enseguida dice que esperes {$wait} s (su ventana es de 60 s)");
    }

    /** La copia del registro de correos no guarda el código (`HidesSecretsInCopy`); el buzón nuevo lo recibe entero. */
    public function test_the_sent_copy_hides_the_code(): void
    {
        $user = $this->holder();
        $this->requestChange($user);

        $row = EmailSend::query()->where('mail_key', 'verify_pending_email')->sole();
        $this->assertSame(self::NEW, $row->recipient);
        $this->assertSame(0, preg_match('/\d{3} \d{3}/', (string) $row->subject.' '.$row->html), 'ni en el asunto ni en la copia');
        $this->assertStringContainsString('••• •••', (string) $row->subject);
    }
}
