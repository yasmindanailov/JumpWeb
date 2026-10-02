<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Identity\Models\LoginCode;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\EmailCodeLogin;
use App\Domain\Identity\Services\LoginCodes;
use App\Domain\Identity\Services\LoginGate;
use App\Domain\Identity\Services\RememberedDevice;
use App\Domain\Platform\Models\EmailSend;
use App\Notifications\LoginCode as LoginCodeMail;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Mockery;
use Symfony\Component\Mime\Email;
use Tests\Feature\Api\ApiTestCase;

/**
 * A1 de `docs/specs/acceso-con-codigo.md` (§4.2–§4.4, §6) — **entrar con un código al correo, por la API**: la puerta
 * (`POST /auth/code`), el código en `POST /auth/login` (la sesión, recordada 90 días) y en `POST /auth/tokens` (la app),
 * y el alta sin contraseña.
 *
 * Lo que se prueba aquí no es el código —eso es `LoginCodesTest`— sino lo que lo rodea: que la puerta diga por dónde
 * seguir y SOLO envíe a una cuenta que existe; que el correo salga tras la respuesta y no espere a la cola; que los
 * límites de pedir estén y que verificar comparta los cubos de la contraseña (`SEC-06`); que el código no quede en la
 * copia del registro de correos ni en el rastro; y que la supresión y la poda se lleven las filas (`RGPD-01`).
 * Arnés: `scripts/mutar-acceso-codigo.sh`.
 */
class AuthCodeTest extends ApiTestCase
{
    private const EMAIL = 'ana.puerta@example.test';

    private function customer(string $email = self::EMAIL, bool $verified = true): User
    {
        return User::factory()->create(['email' => $email, 'email_verified_at' => $verified ? now() : null]);
    }

    /** Como la llamaría el cajón o la isla: con `Origin` de un dominio *stateful*, que es lo que monta la sesión. */
    private function fromSpa(string $path, array $payload = []): TestResponse
    {
        return $this->withHeader('Origin', (string) config('app.url'))->postJson(self::ROOT.$path, $payload);
    }

    /** El código que llevó el último correo del código a `$user`, leído del ASUNTO como lo lee el cliente. */
    private function codeSentTo(User $user): string
    {
        $sent = Notification::sent($user, LoginCodeMail::class)->last();
        $this->assertInstanceOf(LoginCodeMail::class, $sent, 'no salió ningún código a esa cuenta');

        preg_match('/(\d{3})-(\d{3})/', (string) $sent->toMail($user)->subject, $m);

        return $m[1].$m[2];
    }

    private function recallerName(): string
    {
        return Auth::guard('web')->getRecallerName();
    }

    // ── La puerta ────────────────────────────────────────────────────────────────────────────────

    public function test_the_gate_sends_a_code_to_an_existing_account(): void
    {
        Notification::fake();
        $user = $this->customer();

        $this->postJson(self::ROOT.'/auth/code', ['email' => self::EMAIL])
            ->assertOk()
            ->assertValidRequest()
            ->assertValidResponse(200)
            ->assertExactJson(['next' => 'code']);

        Notification::assertSentToTimes($user, LoginCodeMail::class, 1);
        $this->assertSame(1, LoginCode::query()->where('email', self::EMAIL)->count());
    }

    public function test_a_new_email_goes_to_the_signup_without_any_code(): void
    {
        Notification::fake();

        $this->postJson(self::ROOT.'/auth/code', ['email' => 'nueva@example.test'])
            ->assertOk()
            ->assertValidResponse(200)
            ->assertExactJson(['next' => 'register']);

        Notification::assertNothingSent();
        $this->assertSame(0, LoginCode::query()->count(), 'sin cuenta no se emite nada: el alta no espera a ningún correo');
    }

    /**
     * ⚠️⚠️ El correo sale TRAS la respuesta (§4.3): en la misma petición, pero cuando el cliente ya tiene la suya. Se
     * prueba llamando al servicio sin HTTP: hasta que no corren los aplazados, no ha salido nada.
     */
    public function test_the_code_mail_leaves_after_the_response_not_during_it(): void
    {
        Notification::fake();
        $user = $this->customer();

        app(EmailCodeLogin::class)->request(self::EMAIL, '203.0.113.20');

        Notification::assertNothingSent();

        app(DeferredCallbackCollection::class)->invoke();

        Notification::assertSentToTimes($user, LoginCodeMail::class, 1);
    }

    /** Y no por la cola: en producción la vacía el cron cada minuto, y el código tiene que llegar en segundos. */
    public function test_the_code_mail_does_not_wait_for_the_queue(): void
    {
        Queue::fake();
        $this->customer();

        $this->postJson(self::ROOT.'/auth/code', ['email' => self::EMAIL])->assertOk();

        Queue::assertNothingPushed();
        $this->assertNotNull($this->lastSentEmail(), 'el correo salió en esta petición');
    }

    public function test_one_code_a_minute_per_email_and_the_screen_can_go_on_to_type_it(): void
    {
        Notification::fake();
        $user = $this->customer();

        $this->postJson(self::ROOT.'/auth/code', ['email' => self::EMAIL])->assertOk();

        $limit = $this->postJson(self::ROOT.'/auth/code', ['email' => self::EMAIL])
            ->assertStatus(429)
            ->assertValidResponse(429)
            ->assertHeader('Retry-After')
            ->assertJsonPath('error.code', 'too_many_requests')
            ->assertJsonPath('error.params.next', 'code');

        // ⚠️ La espera es la del MINUTO, el único límite agotado: la ventana de la hora corre con 1 de 5 y decía «espera una
        // hora» (3.599 s; medido en la sonda del cajón el 01-10, `EmailCodeLogin::secondsToWait`).
        $wait = (int) $limit->json('error.params.retry_after');
        $this->assertGreaterThan(0, $wait);
        $this->assertLessThanOrEqual(60, $wait, "pedir otro antes del minuto dice que esperes {$wait} s");

        Notification::assertSentToTimes($user, LoginCodeMail::class, 1);

        $this->travel(61)->seconds();

        $this->postJson(self::ROOT.'/auth/code', ['email' => self::EMAIL])->assertOk();
        Notification::assertSentToTimes($user, LoginCodeMail::class, 2);
    }

    public function test_five_codes_an_hour_per_email(): void
    {
        Notification::fake();
        $user = $this->customer();

        foreach (range(1, EmailCodeLogin::MAX_PER_EMAIL_PER_HOUR) as $ignored) {
            $this->postJson(self::ROOT.'/auth/code', ['email' => self::EMAIL])->assertOk();
            $this->travel(61)->seconds();
        }

        $limit = $this->postJson(self::ROOT.'/auth/code', ['email' => self::EMAIL])
            ->assertStatus(429)
            ->assertJsonPath('error.params.next', 'code');

        // Y con la HORA agotada, la de la hora: más de un minuto.
        $this->assertGreaterThan(60, (int) $limit->json('error.params.retry_after'), 'con los cinco de la hora gastados, la espera es la de la hora');

        Notification::assertSentToTimes($user, LoginCodeMail::class, EmailCodeLogin::MAX_PER_EMAIL_PER_HOUR);
    }

    /** El techo por IP cuenta TODAS las peticiones, con cuenta o sin ella: es lo que acota el barrido de correos. */
    public function test_ten_requests_a_minute_per_ip_with_or_without_an_account(): void
    {
        Notification::fake();

        foreach (range(1, EmailCodeLogin::MAX_PER_IP) as $i) {
            $this->postJson(self::ROOT.'/auth/code', ['email' => "barrido{$i}@example.test"])->assertOk();
        }

        $this->postJson(self::ROOT.'/auth/code', ['email' => 'barrido-otro@example.test'])
            ->assertStatus(429)
            ->assertValidResponse(429)
            ->assertJsonMissingPath('error.params.next');
    }

    public function test_the_gate_validates_the_email(): void
    {
        $this->postJson(self::ROOT.'/auth/code', ['email' => 'no-es-un-correo'])
            ->assertStatus(422)
            ->assertValidResponse(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    // ── Verificar: la sesión y el token ─────────────────────────────────────────────────────────

    /**
     * ⚠️⚠️ `#858` (`[DECIDIDO owner]`): recordado 90 días SOLO si lo pide («Mantener la sesión iniciada», `remember`). Una
     * cookie de autenticación persistente no está exenta de consentimiento (GT29, dictamen 4/2012, §3.2): la pide quien
     * marca la casilla, que va SIN marcar.
     */
    public function test_the_code_remembers_the_device_for_ninety_days_only_when_asked(): void
    {
        Notification::fake();
        $user = $this->customer();
        $this->postJson(self::ROOT.'/auth/code', ['email' => self::EMAIL])->assertOk();

        $response = $this->fromSpa('/auth/login', ['email' => self::EMAIL, 'code' => $this->codeSentTo($user), 'remember' => true])
            ->assertOk()
            ->assertValidRequest()
            ->assertValidResponse(200)
            ->assertJsonPath('id', $user->id);

        $this->assertAuthenticatedAs($user->fresh());

        $cookie = $response->getCookie($this->recallerName(), false);
        $this->assertNotNull($cookie, 'con la casilla, el dispositivo queda recordado: la cookie de recuerdo sale con el inicio de sesión');
        $this->assertEqualsWithDelta(now()->addMinutes(RememberedDevice::MINUTES)->timestamp, $cookie->getExpiresTime(), 5);
    }

    public function test_without_asking_the_code_opens_a_plain_session(): void
    {
        Notification::fake();
        $user = $this->customer();
        $this->postJson(self::ROOT.'/auth/code', ['email' => self::EMAIL])->assertOk();

        $response = $this->fromSpa('/auth/login', ['email' => self::EMAIL, 'code' => $this->codeSentTo($user)])
            ->assertOk()
            ->assertValidResponse(200);

        $this->assertAuthenticatedAs($user->fresh());
        $this->assertNull($response->getCookie($this->recallerName(), false), 'sin pedirlo, ninguna cookie de recuerdo');
    }

    public function test_a_wrong_code_is_the_generic_401(): void
    {
        Notification::fake();
        $user = $this->customer();
        $this->postJson(self::ROOT.'/auth/code', ['email' => self::EMAIL])->assertOk();
        $code = $this->codeSentTo($user);
        $wrong = str_pad((string) (((int) $code + 1) % 1_000_000), 6, '0', STR_PAD_LEFT);

        $this->fromSpa('/auth/login', ['email' => self::EMAIL, 'code' => $wrong])
            ->assertStatus(401)
            ->assertValidResponse(401)
            ->assertJsonPath('error.code', 'invalid_credentials');

        $this->assertGuest('web');
    }

    /**
     * ⚠️⚠️ `SEC-06`: verificar el código pasa por los cubos de `LoginGate` (no por unos propios). Cinco códigos malos
     * agotan el cubo (correo, IP), y el código BUENO ya no entra hasta que pase el minuto, ni por el login ni por la app.
     * (Hasta la A5, `#869`, los fallos venían de la contraseña y del código, que compartían los cubos.)
     */
    public function test_the_code_goes_through_the_shared_buckets(): void
    {
        Notification::fake();
        $user = $this->customer();
        $this->postJson(self::ROOT.'/auth/code', ['email' => self::EMAIL])->assertOk();
        $code = $this->codeSentTo($user);
        $wrong = $code === '000000' ? '000001' : '000000';

        foreach (range(1, LoginGate::MAX_ATTEMPTS) as $ignored) {
            $this->fromSpa('/auth/login', ['email' => self::EMAIL, 'code' => $wrong])->assertStatus(401);
        }

        $this->fromSpa('/auth/login', ['email' => self::EMAIL, 'code' => $code])
            ->assertStatus(429)
            ->assertValidResponse(429);

        $this->postJson(self::ROOT.'/auth/tokens', ['email' => self::EMAIL, 'code' => $code, 'device_name' => 'iPhone de Ana'])
            ->assertStatus(429);
    }

    /** Desde la A5 (`#869`) el código es la ÚNICA credencial: sin él, 422 —también si llega una contraseña—. */
    public function test_the_code_is_required_and_a_password_is_no_alternative(): void
    {
        $this->customer();

        $this->fromSpa('/auth/login', ['email' => self::EMAIL, 'password' => 'password'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonStructure(['error' => ['fields' => ['code']]]);

        $this->fromSpa('/auth/login', ['email' => self::EMAIL])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->assertGuest('web');
    }

    /** Un código leído en ese buzón prueba que es suyo: confirma el correo que el alta dejó sin confirmar (§4.4). */
    public function test_the_code_confirms_an_unconfirmed_email(): void
    {
        Notification::fake();
        $user = $this->customer(verified: false);
        $this->postJson(self::ROOT.'/auth/code', ['email' => self::EMAIL])->assertOk();

        $this->fromSpa('/auth/login', ['email' => self::EMAIL, 'code' => $this->codeSentTo($user)])->assertOk();

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_the_app_gets_a_token_with_the_code_and_the_code_is_spent(): void
    {
        Notification::fake();
        $user = $this->customer();
        $this->postJson(self::ROOT.'/auth/code', ['email' => self::EMAIL])->assertOk();
        $code = $this->codeSentTo($user);

        $this->postJson(self::ROOT.'/auth/tokens', ['email' => self::EMAIL, 'code' => $code, 'device_name' => 'iPhone de Ana'])
            ->assertCreated()
            ->assertValidRequest()
            ->assertValidResponse(201)
            ->assertJsonPath('user.id', $user->id);

        $this->assertGuest('web');
        $this->assertSame(1, $user->tokens()->count());

        $this->postJson(self::ROOT.'/auth/tokens', ['email' => self::EMAIL, 'code' => $code, 'device_name' => 'iPhone de Ana'])
            ->assertStatus(401);
    }

    // ── El alta sin contraseña ──────────────────────────────────────────────────────────────────

    /** El alta entra con la sesión de siempre: recordar el dispositivo solo se pide al entrar con el código (`#858`). */
    public function test_a_customer_signs_up_without_a_password_and_without_being_remembered(): void
    {
        $response = $this->fromSpa('/auth/register', ['name' => 'Ana Sin Clave', 'email' => 'sin.clave@example.test'])
            ->assertCreated()
            ->assertValidRequest();

        $user = User::query()->where('email', 'sin.clave@example.test')->sole();
        $this->assertNull($user->getRawOriginal('password'), 'la cuenta nace SIN contraseña');
        $this->assertAuthenticatedAs($user);
        $this->assertNull($response->getCookie($this->recallerName(), false), 'el alta no deja una cookie de recuerdo que nadie pidió');

        // Y ninguna contraseña la abre: desde la A5 (`#869`) ni siquiera es una credencial que la puerta acepte.
        Auth::forgetGuards();
        $this->fromSpa('/auth/login', ['email' => 'sin.clave@example.test', 'password' => 'password'])
            ->assertStatus(422);
    }

    // ── Lo que NO se guarda ─────────────────────────────────────────────────────────────────────

    /**
     * ⚠️⚠️ El registro de correos salientes guarda la COPIA de cada correo para enseñarla en el panel (`#794`). Con este,
     * esa copia sería el código en claro en la base de datos y a la vista de quien tenga el permiso del registro. Se
     * tapa en el asunto y en la copia; el cliente lo recibe entero.
     */
    public function test_the_sent_copy_hides_the_code_and_the_customer_gets_it_whole(): void
    {
        $this->customer();

        $this->postJson(self::ROOT.'/auth/code', ['email' => self::EMAIL])->assertOk();

        $sent = $this->lastSentEmail();
        $this->assertNotNull($sent);
        // «482-913 es tu código para entrar» (`#867`): el código, lo PRIMERO del asunto y con guion, como lo enseña el aviso.
        $this->assertSame(1, preg_match('/^(\d{3})-(\d{3}) /', (string) $sent->getSubject(), $m), 'el código viaja en el asunto');
        $shown = trim($m[0]);
        $this->assertStringContainsString($shown, (string) $sent->getHtmlBody(), 'y como titular del correo');

        $row = EmailSend::query()->sole();
        $this->assertSame('login_code', $row->mail_key);
        $this->assertStringNotContainsString($shown, (string) $row->subject);
        $this->assertStringNotContainsString($shown, (string) $row->html);
        $this->assertStringContainsString('•••••••', (string) $row->subject);
        $this->assertStringContainsString('•••••••', (string) $row->html);
    }

    public function test_the_trail_carries_neither_the_email_nor_the_code(): void
    {
        Notification::fake();
        Log::spy();
        $this->customer();

        $this->postJson(self::ROOT.'/auth/code', ['email' => self::EMAIL])->assertOk();

        Log::shouldHaveReceived('info')->with('auth.code_requested', Mockery::on(
            static fn (array $context): bool => array_keys($context) === ['user_id', 'ip'],
        ));
    }

    /** `RGPD-01`: la supresión se lleva los códigos del titular —los de su correo y los del pendiente—, no los de otro. */
    public function test_anonymize_takes_the_codes_of_the_holder_and_only_theirs(): void
    {
        $user = $this->customer();
        $user->forceFill(['pending_email' => 'nuevo.correo@example.test'])->save();
        $codes = app(LoginCodes::class);
        $codes->issue(self::EMAIL, LoginCode::PURPOSE_LOGIN, '203.0.113.1');
        $codes->issue('nuevo.correo@example.test', LoginCode::PURPOSE_LOGIN, '203.0.113.1');
        $codes->issue('otra.persona@example.test', LoginCode::PURPOSE_LOGIN, '203.0.113.2');

        $user->anonymize();

        $this->assertSame(['otra.persona@example.test'], LoginCode::query()->pluck('email')->all(), 'control: el de otra persona sigue');
    }

    public function test_rows_older_than_a_day_are_pruned_every_night(): void
    {
        $codes = app(LoginCodes::class);
        $codes->issue('vieja@example.test', LoginCode::PURPOSE_LOGIN, '203.0.113.1');
        $this->travel(LoginCode::RETENTION_HOURS + 1)->hours();
        $codes->issue('reciente@example.test', LoginCode::PURPOSE_LOGIN, '203.0.113.1');

        $this->artisan('model:prune', ['--model' => [LoginCode::class]])->assertSuccessful();

        $this->assertSame(['reciente@example.test'], LoginCode::query()->pluck('email')->all());

        $prune = collect(app(Schedule::class)->events())
            ->filter(static fn ($event): bool => str_contains((string) $event->command, 'model:prune'));
        $this->assertStringContainsString("--model='".LoginCode::class."'", (string) $prune->first()?->command, 'la poda diaria incluye los códigos');
    }

    private function lastSentEmail(): ?Email
    {
        /** @var ArrayTransport $transport */
        $transport = app('mailer')->getSymfonyTransport();
        $email = $transport->messages()->last()?->getOriginalMessage();

        return $email instanceof Email ? $email : null;
    }
}
