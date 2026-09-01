<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\AccountProfile;
use App\Notifications\EmailChangeRequested;
use App\Notifications\VerifyPendingEmail;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Tests\Feature\Api\ApiTestCase;

/**
 * **Tanda 2 · paso 7** — `PATCH /api/v1/me` y el ciclo del cambio de correo
 * (`specs/area-cliente.md` §9).
 *
 * Lo que estas guardas protegen, más allá de «guarda el nombre»:
 *  - que **el correo NO cambie al guardar**: se solicita, y el vigente sigue valiendo. Es lo que
 *    impide que quien entre en una sesión ajena deje al dueño fuera de su propia cuenta;
 *  - que salgan **DOS** avisos —al buzón nuevo con el enlace y al viejo para delatar el intento— y
 *    que el segundo lleve la dirección **enmascarada**;
 *  - que la doble comprobación de unicidad mire también el `pending_email` **de otros**;
 *  - y que el reenvío tenga su cooldown, que protege un buzón ajeno.
 */
class MeProfileTest extends ApiTestCase
{
    private const PASSWORD = 'contrasena-actual-9';

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    private function holder(string $email = 'titular@ejemplo.test'): User
    {
        $user = new User;
        $user->name = 'Titular';
        $user->email = $email;
        $user->phone = '600000000';
        $user->locale = 'es';
        $user->password = self::PASSWORD;
        $user->email_verified_at = now();
        $user->save();

        RateLimiter::clear('account-credentials:'.$user->id.'|127.0.0.1');
        RateLimiter::clear('pending-email-resend:'.$user->id);

        return $user;
    }

    /** @param array<string, mixed> $overrides */
    private function payload(User $user, array $overrides = []): array
    {
        return array_merge([
            'name' => $user->name,
            'phone' => $user->phone,
            'locale' => $user->locale,
            'email' => $user->email,
        ], $overrides);
    }

    // ── Sin tocar el correo ───────────────────────────────────────────────────────────────────

    public function test_it_saves_the_plain_fields_without_asking_for_the_password(): void
    {
        $user = $this->holder();

        $this->actingAs($user)
            ->patchJson(self::ROOT.'/me', $this->payload($user, ['name' => 'Ana', 'phone' => '611111111']))
            ->assertOk()
            ->assertValidResponse(200)
            ->assertJsonPath('name', 'Ana');

        $user->refresh();
        $this->assertSame('Ana', $user->name);
        $this->assertSame('611111111', $user->phone);
        $this->assertNull($user->pending_email, 'no se ha pedido ningún cambio de correo');
    }

    // ── El ciclo del correo ───────────────────────────────────────────────────────────────────

    /**
     * ⚠️ **La guarda de fondo**: el correo vigente NO se toca. Si esto se rompiera, quien entrara en
     * una sesión ajena dejaría al dueño fuera de su propia cuenta con una sola petición.
     */
    public function test_changing_the_email_only_request_s_it_and_the_old_one_keeps_working(): void
    {
        $user = $this->holder();

        $this->actingAs($user)
            ->patchJson(self::ROOT.'/me', $this->payload($user, [
                'email' => 'nueva@ejemplo.test',
                'current_password' => self::PASSWORD,
            ]))
            ->assertOk()
            ->assertJsonPath('email', 'titular@ejemplo.test')
            ->assertJsonPath('pending_email', 'nueva@ejemplo.test');

        $user->refresh();
        $this->assertSame('titular@ejemplo.test', $user->email, 'el correo vigente ha cambiado sin confirmar');
        $this->assertSame('nueva@ejemplo.test', $user->pending_email);
        $this->assertNotNull($user->pending_email_sent_at);
    }

    /** ⚠️ DOS avisos, y el del buzón viejo lleva la dirección nueva **enmascarada**. */
    public function test_it_warns_both_mailboxes_and_masks_the_new_address(): void
    {
        $user = $this->holder();

        $this->actingAs($user)
            ->patchJson(self::ROOT.'/me', $this->payload($user, [
                'email' => 'nueva@ejemplo.test',
                'current_password' => self::PASSWORD,
            ]))
            ->assertOk();

        Notification::assertSentTo($user, VerifyPendingEmail::class);
        Notification::assertSentTo($user, EmailChangeRequested::class, function (EmailChangeRequested $notification): bool {
            // La notificación guarda la dirección ya enmascarada en una propiedad pública con su
            // nombre —`newEmailMasked`—, así que se lee tal cual: buscarla por reflexión sería
            // frágil y no diría nada más.
            $this->assertNotSame(
                'nueva@ejemplo.test', $notification->newEmailMasked,
                'el aviso al buzón VIEJO publica la dirección nueva entera'
            );
            $this->assertStringContainsString('*', $notification->newEmailMasked);
            $this->assertStringEndsWith('@ejemplo.test', $notification->newEmailMasked,
                'sin el dominio, el dueño no puede reconocer si el intento fue suyo');

            return true;
        });
    }

    public function test_changing_the_email_needs_the_password_and_a_wrong_one_changes_nothing(): void
    {
        $user = $this->holder();

        // Sin contraseña: el cuerpo ni siquiera es válido.
        $this->actingAs($user)
            ->patchJson(self::ROOT.'/me', $this->payload($user, ['email' => 'nueva@ejemplo.test']))
            ->assertStatus(422);

        $response = $this->actingAs($user)
            ->patchJson(self::ROOT.'/me', $this->payload($user, [
                'email' => 'nueva@ejemplo.test',
                'name' => 'Cambiado',
                'current_password' => 'no-es-esta',
            ]));

        $response->assertStatus(422)->assertValidResponse(422);
        $this->assertNotEmpty($response->json('error.fields.current_password'));

        $user->refresh();
        $this->assertNull($user->pending_email);
        // ⚠️ **Y tampoco se guarda el nombre**: la reconfirmación protege la petición ENTERA, no solo
        // el correo. Si los campos «inocentes» se aplicaran igual, bastaría con adjuntar un cambio de
        // correo fallido para editar el perfil ajeno sin saber la contraseña.
        $this->assertSame('Titular', $user->name);
    }

    /** ⚠️ La doble unicidad: no solo el `email` de otro, también su `pending_email`. */
    public function test_it_rejects_an_email_another_account_already_has_or_is_claiming(): void
    {
        $user = $this->holder();

        $otro = $this->holder('otro@ejemplo.test');
        $otro->pending_email = 'reclamado@ejemplo.test';
        $otro->save();

        foreach (['otro@ejemplo.test', 'reclamado@ejemplo.test'] as $taken) {
            $response = $this->actingAs($user)
                ->patchJson(self::ROOT.'/me', $this->payload($user, [
                    'email' => $taken,
                    'current_password' => self::PASSWORD,
                ]));

            $response->assertStatus(422);
            $this->assertNotEmpty($response->json('error.fields.email'), "«{$taken}» se ha aceptado");
        }
    }

    /**
     * ⚠️⚠️ **Y la REGLA se prueba aparte, porque el caso de arriba NO la distingue.** Medido por
     * mutación: quitando `Rule::unique('users','pending_email')` el test anterior **seguía verde** —
     * la UNIQUE de la base captura el choque igual y el servicio lo traduce al mismo 422—. Los dos
     * caminos acaban en la misma respuesta, así que mirar la respuesta no dice cuál actuó.
     *
     * ▶ La regla no es redundante con la base: **evita el intento**, da el mensaje de validación
     * limpio antes de tocar disco y es la que sigue valiendo si algún día se guarda por otra vía. Se
     * comprueba donde vive.
     */
    public function test_the_rules_themselves_reject_an_email_another_account_is_claiming(): void
    {
        $user = $this->holder();

        $otro = $this->holder('otro@ejemplo.test');
        $otro->pending_email = 'reclamado@ejemplo.test';
        $otro->save();

        $validator = Validator::make(
            $this->payload($user, ['email' => 'reclamado@ejemplo.test']),
            AccountProfile::rules($user),
        );

        $this->assertTrue(
            $validator->fails(),
            'las reglas del perfil ya no miran el `pending_email` de OTROS: dos titulares podrían '.
            'pedir el mismo correo y el segundo enlace confirmado chocaría contra la UNIQUE'
        );
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    /** Re-pedir el propio pendiente no choca consigo mismo. */
    public function test_asking_again_for_the_same_pending_email_is_allowed(): void
    {
        $user = $this->holder();
        $user->pending_email = 'nueva@ejemplo.test';
        $user->save();

        $this->actingAs($user)
            ->patchJson(self::ROOT.'/me', $this->payload($user, [
                'email' => 'nueva@ejemplo.test',
                'current_password' => self::PASSWORD,
            ]))
            ->assertOk();
    }

    // ── Cancelar y reenviar ───────────────────────────────────────────────────────────────────

    public function test_cancelling_clears_the_pending_and_is_idempotent(): void
    {
        $user = $this->holder();
        $user->pending_email = 'nueva@ejemplo.test';
        $user->pending_email_sent_at = now();
        $user->save();

        $this->actingAs($user)->deleteJson(self::ROOT.'/me/pending-email')->assertNoContent();

        $user->refresh();
        $this->assertNull($user->pending_email);
        $this->assertNull($user->pending_email_sent_at);

        // ⚠️ Segunda vez: **la misma respuesta**. Quien pulsa dos veces no ha hecho nada malo.
        $this->actingAs($user)->deleteJson(self::ROOT.'/me/pending-email')->assertNoContent();
    }

    public function test_resending_sends_again_and_then_waits(): void
    {
        $user = $this->holder();
        $user->pending_email = 'nueva@ejemplo.test';
        $user->pending_email_sent_at = now()->subMinutes(30);
        $user->save();

        $this->actingAs($user)->postJson(self::ROOT.'/me/pending-email/resend')->assertNoContent();

        Notification::assertSentTo($user, VerifyPendingEmail::class);

        // ⚠️ **Se resella el envío**: la validez del enlace cuenta desde el último, no desde el
        // primero. Sin esto, el reenviado caducaría antes de llegar.
        $this->assertTrue($user->fresh()->pending_email_sent_at->greaterThan(now()->subMinute()));

        $blocked = $this->actingAs($user)->postJson(self::ROOT.'/me/pending-email/resend');
        $blocked->assertStatus(429)->assertValidResponse(429);
        $this->assertNotEmpty($blocked->headers->get('Retry-After'));
    }

    /** ⚠️ Sin nada pendiente es un 204 y **no manda correo**: no es un error, no hay nada que hacer. */
    public function test_resending_without_anything_pending_is_a_no_op(): void
    {
        $user = $this->holder();

        $this->actingAs($user)->postJson(self::ROOT.'/me/pending-email/resend')->assertNoContent();

        Notification::assertNothingSent();
    }

    // ── `#327` · reenviar MI verificación (con sesión y sin decir el correo) ──────────────────

    /**
     * El caso que motiva el endpoint: **se puede entrar sin haber verificado**, y ahí el área de
     * cuenta ofrece la salida. El cliente no manda cuerpo: quién es lo dice el guard.
     */
    public function test_an_unverified_holder_can_resend_their_own_verification(): void
    {
        $user = $this->holder();
        $user->forceFill(['email_verified_at' => null])->save();

        $this->actingAs($user)->postJson(self::ROOT.'/me/email/resend')
            ->assertNoContent()
            ->assertValidResponse(204);

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    /** ⚠️ Con el correo ya verificado es un no-op silencioso: no hay nada que reenviar. */
    public function test_resending_my_verification_when_already_verified_sends_nothing(): void
    {
        $this->actingAs($this->holder())->postJson(self::ROOT.'/me/email/resend')->assertNoContent();

        Notification::assertNothingSent();
    }

    /**
     * ⚠️⚠️ **El cooldown por buzón destinatario sigue mandando**, y es el que de verdad protege: el
     * endpoint reutiliza `SelfSignup::resendVerification()` entero en vez de mandar el correo por su
     * cuenta. Si alguien lo reimplementara «más simple», este caso se pondría rojo.
     */
    public function test_resending_my_verification_twice_only_sends_once(): void
    {
        $user = $this->holder();
        $user->forceFill(['email_verified_at' => null])->save();

        $this->actingAs($user)->postJson(self::ROOT.'/me/email/resend')->assertNoContent();
        $this->actingAs($user)->postJson(self::ROOT.'/me/email/resend')->assertNoContent();

        Notification::assertSentToTimes($user, VerifyEmail::class, 1);
    }

    // ── Puerta ────────────────────────────────────────────────────────────────────────────────

    public function test_the_three_endpoints_reject_an_anonymous_request(): void
    {
        $this->patchJson(self::ROOT.'/me', ['name' => 'x', 'phone' => 'y', 'locale' => 'es', 'email' => 'a@b.test'])
            ->assertStatus(401);
        $this->deleteJson(self::ROOT.'/me/pending-email')->assertStatus(401);
        $this->postJson(self::ROOT.'/me/pending-email/resend')->assertStatus(401);
        $this->postJson(self::ROOT.'/me/email/resend')->assertStatus(401);
    }
}
