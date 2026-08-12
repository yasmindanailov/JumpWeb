<?php

namespace Tests\Feature\Auth;

use App\Domain\Booking\Models\Order;
use App\Domain\Identity\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function verificationUrl(User $user, ?string $hash = null): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => $hash ?? sha1($user->getEmailForVerification()),
        ]);
    }

    public function test_notice_page_loads(): void
    {
        $this->get(route('verification.notice'))
            ->assertOk()
            ->assertSee(__('account.verify.title'));
    }

    public function test_signed_link_verifies_email_and_logs_in(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get($this->verificationUrl($user))
            ->assertRedirect(route('account.orders')) // pay-first: el enlace lleva a «mis reservas»
            ->assertSessionHas('status', 'email-verified');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertAuthenticatedAs($user);
    }

    public function test_link_with_wrong_hash_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get($this->verificationUrl($user, sha1('otro@email.com')))
            ->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_unsigned_link_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get(route('verification.verify', ['id' => $user->id, 'hash' => sha1($user->email)]))
            ->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_unknown_user_id_returns_403_not_404(): void
    {
        // Anti-enumeración por id: si el id no existe, devolver 403 (mismo código que para
        // hash inválido) en lugar de 404, que sería distinguible.
        $url = URL::temporarySignedRoute('verification.verify',
            now()->addMinutes(60), ['id' => 999_999, 'hash' => sha1('cualquiera@example.com')]);

        $this->get($url)->assertForbidden();
    }

    public function test_expired_link_is_rejected(): void
    {
        // El middleware `signed` valida la firma + caducidad. Tras la ventana de 60 min,
        // el enlace deja de ser válido aunque la firma sea correcta.
        $user = User::factory()->unverified()->create();

        $expiredUrl = URL::temporarySignedRoute('verification.verify',
            now()->subMinute(), ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]);

        $this->get($expiredUrl)->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_verification_does_not_touch_reservations(): void
    {
        // Pay-first (decisión clienta 2026-06-14): verificar el email YA NO confirma ni caduca
        // reservas (antes dejaba el pedido «firme pero sin pagar», estado incoherente; #76/#78). El
        // pago es lo que cierra la reserva (auto-verify en RedsysReturnHandler). Aquí: un pedido
        // pendiente del usuario NO debe alterarse al verificar el email.
        $user = User::factory()->unverified()->create();

        $pending = Order::create([
            'user_id' => $user->id, 'code' => 'JJ-'.Str::upper(Str::random(6)),
            'status' => Order::STATUS_PENDING, 'expires_at' => now()->addHour(),
        ]);
        $originalExpiry = $pending->expires_at;

        $this->get($this->verificationUrl($user))->assertRedirect(route('account.orders'));

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $fresh = $pending->fresh();
        $this->assertSame(Order::STATUS_PENDING, $fresh->status);                  // intacto
        $this->assertNotNull($fresh->expires_at);                                  // NO se hizo firme
        $this->assertEquals($originalExpiry->timestamp, $fresh->expires_at->timestamp); // sin cambios
    }

    public function test_authenticated_unverified_user_can_resend_verification_from_notice(): void
    {
        // Pay-first: quien se registró en la compra y abandonó sin pagar queda sin verificar; al
        // intentar entrar a «mi cuenta» ve el aviso y puede REENVIARSE el correo (ruta nueva).
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertRedirect()
            ->assertSessionHas('status', 'verification-link-sent');

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_resend_is_noop_for_already_verified_user(): void
    {
        Notification::fake();
        $user = User::factory()->create(); // verificado por defecto

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertRedirect(route('account.orders'));

        Notification::assertNothingSent();
    }
}
