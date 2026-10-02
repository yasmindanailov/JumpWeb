<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Identity\Models\LoginCode;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserIdentity;
use App\Domain\Identity\Services\AccountCredentials;
use App\Domain\Identity\Services\LoginCodes;
use App\Notifications\ConfirmationCode;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Api\ApiTestCase;

/**
 * A2a de `docs/specs/acceso-con-codigo.md` (§4.9, `DECISIONES #855`) — **las acciones sensibles, confirmadas con un código
 * al correo de la cuenta** en vez de la contraseña: borrar la cuenta, cambiar el correo, desvincular Google y cerrar las
 * demás sesiones.
 *
 * Lo que se prueba: que el código se pida con techo y diga PARA QUÉ es; que cada una de las cuatro acciones lo acepte, y
 * con el código malo no haga NADA (422 sobre `code`); que un código de ENTRAR no confirme; y, desde la A5 (`#869`), que
 * sea la ÚNICA reconfirmación. Que «cerrar las demás» cierre también las SESIONES vivas es `SessionBindingTest`.
 * Arnés: `scripts/mutar-acceso-codigo.sh`.
 */
class MeConfirmationCodeTest extends ApiTestCase
{
    private const EMAIL = 'ana.confirma@example.test';

    private const IP = '203.0.113.30';

    private function holder(): User
    {
        return User::factory()->create(['email' => self::EMAIL, 'email_verified_at' => now()]);
    }

    /** Un código `confirm` vivo para la cuenta, sin pasar por el correo (lo que se prueba aquí es gastarlo). */
    private function confirmCode(string $email = self::EMAIL): string
    {
        return app(LoginCodes::class)->issue($email, LoginCode::PURPOSE_CONFIRM, self::IP);
    }

    private function wrong(string $code): string
    {
        return str_pad((string) (((int) $code + 1) % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    // ── Pedir el código ─────────────────────────────────────────────────────────────────────────

    public function test_the_code_goes_to_the_account_email_and_says_what_it_is_for(): void
    {
        Notification::fake();
        $user = $this->holder();

        $this->actingAs($user)
            ->postJson(self::ROOT.'/me/confirm-code', ['action' => 'delete_account'])
            ->assertStatus(202)
            ->assertValidRequest()
            ->assertValidResponse(202);

        Notification::assertSentToTimes($user, ConfirmationCode::class, 1);
        $mail = Notification::sent($user, ConfirmationCode::class)->first()->toMail($user);
        $this->assertContains(__('emails.confirmation_code.for', ['action' => __('emails.confirmation_code.actions.delete_account')]), $mail->introLines);
        $this->assertSame(1, LoginCode::query()->where('email', self::EMAIL)->where('purpose', LoginCode::PURPOSE_CONFIRM)->count());
    }

    public function test_one_code_a_minute_per_account(): void
    {
        Notification::fake();
        $user = $this->holder();

        $this->actingAs($user)->postJson(self::ROOT.'/me/confirm-code', ['action' => 'close_sessions'])->assertStatus(202);

        $limit = $this->actingAs($user)->postJson(self::ROOT.'/me/confirm-code', ['action' => 'close_sessions'])
            ->assertStatus(429)
            ->assertValidResponse(429)
            ->assertHeader('Retry-After');

        // ⚠️ La espera del MINUTO, el único límite agotado (`EmailCodeLogin::secondsToWait`): decía la de la hora, 3.599 s.
        $wait = (int) $limit->headers->get('Retry-After');
        $this->assertGreaterThan(0, $wait);
        $this->assertLessThanOrEqual(60, $wait, "pedir otro antes del minuto dice que esperes {$wait} s");

        Notification::assertSentToTimes($user, ConfirmationCode::class, 1);
    }

    public function test_the_action_is_one_of_the_four(): void
    {
        $this->actingAs($this->holder())
            ->postJson(self::ROOT.'/me/confirm-code', ['action' => 'lo_que_sea'])
            ->assertStatus(422)
            ->assertValidResponse(422);

        $this->assertSame(AccountCredentials::CONFIRM_ACTIONS, ['delete_account', 'change_email', 'unlink_google', 'close_sessions']);
    }

    public function test_asking_for_a_code_needs_a_session(): void
    {
        $this->postJson(self::ROOT.'/me/confirm-code', ['action' => 'delete_account'])->assertStatus(401);
    }

    // ── Las cuatro acciones, con el código ─────────────────────────────────────────────────────

    public function test_the_account_is_deleted_with_the_code(): void
    {
        $user = $this->holder();

        $this->actingAs($user)
            ->deleteJson(self::ROOT.'/me', ['code' => $this->confirmCode()])
            ->assertNoContent()
            ->assertValidRequest();

        $this->assertTrue($user->fresh()->isAnonymized());
    }

    /** ⚠️⚠️ Con el código malo NO se borra nada, y el 422 va sobre `code` (que es lo que la pantalla pinta). */
    public function test_a_wrong_code_deletes_nothing_and_answers_on_the_code_field(): void
    {
        $user = $this->holder();
        $code = $this->confirmCode();

        $this->actingAs($user)
            ->deleteJson(self::ROOT.'/me', ['code' => $this->wrong($code)])
            ->assertStatus(422)
            ->assertValidResponse(422)
            ->assertJsonPath('error.fields.code.0', __('api.confirm.wrong_code'));

        $this->assertFalse($user->fresh()->isAnonymized());
    }

    /** Un código de ENTRAR no confirma nada: el propósito va en su huella. */
    public function test_a_login_code_does_not_confirm(): void
    {
        $user = $this->holder();
        $loginCode = app(LoginCodes::class)->issue(self::EMAIL, LoginCode::PURPOSE_LOGIN, self::IP);

        $this->actingAs($user)->deleteJson(self::ROOT.'/me', ['code' => $loginCode])->assertStatus(422);

        $this->assertFalse($user->fresh()->isAnonymized());
    }

    public function test_the_email_change_is_requested_with_the_code(): void
    {
        $user = $this->holder();

        $this->actingAs($user)
            ->patchJson(self::ROOT.'/me', [
                'name' => $user->name, 'phone' => (string) $user->phone, 'locale' => 'es',
                'email' => 'ana.nueva@example.test', 'code' => $this->confirmCode(),
            ])
            ->assertOk()
            ->assertValidRequest()
            ->assertJsonPath('pending_email', 'ana.nueva@example.test');
    }

    public function test_a_wrong_code_does_not_request_the_email_change(): void
    {
        $user = $this->holder();
        $code = $this->confirmCode();

        $this->actingAs($user)
            ->patchJson(self::ROOT.'/me', [
                'name' => $user->name, 'phone' => (string) $user->phone, 'locale' => 'es',
                'email' => 'ana.nueva@example.test', 'code' => $this->wrong($code),
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.fields.code.0', __('api.confirm.wrong_code'));

        $this->assertNull($user->fresh()->pending_email);
    }

    public function test_google_is_unlinked_with_the_code(): void
    {
        $user = $this->holder();
        UserIdentity::create([
            'user_id' => $user->id, 'provider' => UserIdentity::PROVIDER_GOOGLE, 'provider_id' => '110000000000000000077',
            'email_at_link' => self::EMAIL, 'linked_via' => UserIdentity::VIA_LOGIN, 'linked_at' => now()->subDay(),
        ]);

        $this->actingAs($user)
            ->deleteJson(self::ROOT.'/me/identities/google', ['code' => $this->confirmCode()])
            ->assertNoContent()
            ->assertValidRequest();

        $this->assertSame(0, UserIdentity::query()->count());
    }

    public function test_the_other_sessions_are_closed_with_the_code(): void
    {
        $user = $this->holder();

        $this->actingAs($user)
            ->postJson(self::ROOT.'/me/sessions/revoke-others', ['code' => $this->confirmCode()])
            ->assertNoContent()
            ->assertValidRequest();
    }

    public function test_the_code_is_spent_once_used(): void
    {
        $user = $this->holder();
        $code = $this->confirmCode();

        $this->actingAs($user)->postJson(self::ROOT.'/me/sessions/revoke-others', ['code' => $code])->assertNoContent();
        $this->actingAs($user)->postJson(self::ROOT.'/me/sessions/revoke-others', ['code' => $code])->assertStatus(422);
    }

    /**
     * Desde la A5 (`#869`) el código es la ÚNICA reconfirmación: la contraseña de antes ya no es una alternativa (un
     * cliente viejo que la mande recibe el 422 del código que falta), y sin nada tampoco se borra nada. El caso «la
     * contraseña y el código comparten el limitador» se fue con la contraseña: el limitador, con códigos, es de
     * `MePrivacyTest` y `MeCredentialsTest`.
     */
    public function test_the_code_is_the_only_way_to_confirm(): void
    {
        $user = $this->holder();

        $this->actingAs($user)->deleteJson(self::ROOT.'/me', ['current_password' => 'password'])
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['fields' => ['code']]]);
        $this->actingAs($user)->deleteJson(self::ROOT.'/me', [])->assertStatus(422);

        $this->assertFalse($user->fresh()->isAnonymized());
    }
}
