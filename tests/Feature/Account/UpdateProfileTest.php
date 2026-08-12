<?php

namespace Tests\Feature\Account;

use App\Domain\Identity\Models\User;
use App\Livewire\Account\UpdateProfile;
use App\Notifications\EmailChangeRequested;
use App\Notifications\VerifyPendingEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 4.5a — Editar perfil. Cambiar el email usa el patrón `pending_email` (auditoría
 * 2026-05-26, hallazgo A): el email actual NO se sobrescribe hasta que el cliente
 * confirma el nuevo desde su buzón. Mientras tanto, sigue siendo el email válido para
 * login/reset. Reconfirmación de contraseña obligatoria (regla 3 de SEGURIDAD).
 */
class UpdateProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_name_phone_and_locale(): void
    {
        $user = User::factory()->create(['name' => 'Ana', 'phone' => '600000000', 'locale' => 'es']);

        Livewire::actingAs($user)
            ->test(UpdateProfile::class)
            ->set('name', 'Ana López')
            ->set('phone', '611111111')
            ->set('locale', 'en')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('account'));

        $user->refresh();
        $this->assertSame('Ana López', $user->name);
        $this->assertSame('611111111', $user->phone);
        $this->assertSame('en', $user->locale);
        // El email no ha cambiado → sigue verificado.
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_invalid_locale_is_rejected(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(UpdateProfile::class)
            ->set('locale', 'de') // no soportado
            ->call('save')
            ->assertHasErrors('locale');
    }

    public function test_changing_email_requests_change_without_touching_current_email(): void
    {
        // Patrón pending_email: el email actual queda intacto; el nuevo va a `pending_email`
        // y se envía un enlace de confirmación al NUEVO buzón + aviso al VIEJO. Solo al
        // confirmar (controlador EmailChangeController) se aplica el cambio.
        Notification::fake();
        $user = User::factory()->create(['email' => 'ana@example.com']);

        Livewire::actingAs($user)
            ->test(UpdateProfile::class)
            ->set('email', 'nueva@example.com')
            ->set('current_password', 'password')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('account'));

        $user->refresh();
        $this->assertSame('ana@example.com', $user->email);             // sigue intacto
        $this->assertNotNull($user->email_verified_at);                 // sigue verificado
        $this->assertSame('nueva@example.com', $user->pending_email);   // pendiente de confirmar
        $this->assertNotNull($user->pending_email_sent_at);

        Notification::assertSentTo($user, VerifyPendingEmail::class);   // al NUEVO buzón
        Notification::assertSentTo($user, EmailChangeRequested::class); // al VIEJO buzón (anti-takeover)
    }

    public function test_changing_email_without_correct_password_fails(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.com']);

        Livewire::actingAs($user)
            ->test(UpdateProfile::class)
            ->set('email', 'nueva@example.com')
            ->set('current_password', 'incorrecta')
            ->call('save')
            ->assertHasErrors('current_password');

        $user->refresh();
        $this->assertSame('ana@example.com', $user->email);
        $this->assertNull($user->pending_email);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create(['email' => 'ana@example.com']);

        Livewire::actingAs($user)
            ->test(UpdateProfile::class)
            ->set('email', 'taken@example.com')
            ->set('current_password', 'password')
            ->call('save')
            ->assertHasErrors('email');

        $this->assertSame('ana@example.com', $user->fresh()->email);
        $this->assertNull($user->fresh()->pending_email);
    }

    public function test_email_cannot_be_someone_elses_pending_email(): void
    {
        // Si Bob ya está reclamando "compartido@example.com" (pending_email), Alice no puede
        // pedirlo en paralelo: lo bloqueamos en validación para fallar rápido.
        User::factory()->create([
            'email' => 'bob@example.com',
            'pending_email' => 'compartido@example.com',
        ]);
        $user = User::factory()->create(['email' => 'ana@example.com']);

        Livewire::actingAs($user)
            ->test(UpdateProfile::class)
            ->set('email', 'compartido@example.com')
            ->set('current_password', 'password')
            ->call('save')
            ->assertHasErrors('email');

        $this->assertNull($user->fresh()->pending_email);
    }

    public function test_cancel_pending_email_clears_the_request(): void
    {
        $user = User::factory()->create([
            'email' => 'ana@example.com',
            'pending_email' => 'nueva@example.com',
            'pending_email_sent_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(UpdateProfile::class)
            ->call('cancelEmailChange')
            ->assertRedirect(route('account'));

        $user->refresh();
        $this->assertNull($user->pending_email);
        $this->assertNull($user->pending_email_sent_at);
        $this->assertSame('ana@example.com', $user->email);
    }

    public function test_resend_pending_email_sends_again_and_refreshes_the_window(): void
    {
        // T2.2 — el cliente puede pedir reenviar el enlace al pending_email; el sent_at se
        // refresca para extender la ventana de caducidad desde el reenvío.
        Notification::fake();
        $sentAt = now()->subMinutes(30);
        $user = User::factory()->create([
            'email' => 'ana@example.com',
            'pending_email' => 'nueva@example.com',
            'pending_email_sent_at' => $sentAt,
        ]);
        RateLimiter::clear('pending-email-resend:'.$user->id);

        Livewire::actingAs($user)
            ->test(UpdateProfile::class)
            ->call('resendPendingEmail');

        Notification::assertSentTo($user, VerifyPendingEmail::class);
        $this->assertTrue($user->fresh()->pending_email_sent_at->gt($sentAt));
    }

    public function test_resend_pending_email_is_rate_limited_per_user(): void
    {
        // Cooldown 1/min para evitar bombardear el nuevo buzón si el atacante tiene sesión.
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'ana@example.com',
            'pending_email' => 'nueva@example.com',
            'pending_email_sent_at' => now(),
        ]);
        RateLimiter::clear('pending-email-resend:'.$user->id);

        $component = Livewire::actingAs($user)->test(UpdateProfile::class);
        $component->call('resendPendingEmail');   // pasa
        $component->call('resendPendingEmail');   // bloqueado por rate limit

        Notification::assertSentToTimes($user, VerifyPendingEmail::class, 1);
        $component->assertHasErrors('current_password');
    }

    public function test_resend_pending_email_does_nothing_without_pending(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'ana@example.com']);

        Livewire::actingAs($user)
            ->test(UpdateProfile::class)
            ->call('resendPendingEmail');

        Notification::assertNothingSent();
    }
}
