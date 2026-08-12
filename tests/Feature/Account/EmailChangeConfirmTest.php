<?php

namespace Tests\Feature\Account;

use App\Models\User;
use App\Notifications\EmailChangeCompleted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Auditoría 2026-05-26 (hallazgo A) — confirmación del cambio de email.
 * El enlace firmado llega al NUEVO buzón. Al pulsarlo, se aplica el cambio y se limpia
 * el pending. Tests de los escenarios de robustez (firma inválida, caducidad, race con
 * otro usuario que registra ese email en el ínterin, id desconocido).
 */
class EmailChangeConfirmTest extends TestCase
{
    use RefreshDatabase;

    private function confirmUrl(User $user, ?string $hash = null, ?int $id = null): string
    {
        return URL::temporarySignedRoute('account.email.confirm', now()->addMinutes(60), [
            'id' => $id ?? $user->id,
            'hash' => $hash ?? sha1((string) $user->pending_email),
        ]);
    }

    public function test_signed_link_applies_the_change_and_clears_the_pending(): void
    {
        $user = User::factory()->create([
            'email' => 'ana@example.com',
            'pending_email' => 'nueva@example.com',
            'pending_email_sent_at' => now(),
        ]);

        $this->get($this->confirmUrl($user))
            ->assertRedirect(route('account'));

        $user->refresh();
        $this->assertSame('nueva@example.com', $user->email);
        $this->assertNotNull($user->email_verified_at); // confirmar implica verificar el nuevo
        $this->assertNull($user->pending_email);
        $this->assertNull($user->pending_email_sent_at);
    }

    public function test_wrong_hash_is_rejected(): void
    {
        $user = User::factory()->create([
            'email' => 'ana@example.com',
            'pending_email' => 'nueva@example.com',
            'pending_email_sent_at' => now(),
        ]);

        $this->get($this->confirmUrl($user, sha1('otro@example.com')))
            ->assertForbidden();

        $this->assertSame('ana@example.com', $user->fresh()->email);
        $this->assertSame('nueva@example.com', $user->fresh()->pending_email);
    }

    public function test_no_pending_returns_403(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.com']); // sin pending

        $this->get($this->confirmUrl($user, sha1('cualquiera@example.com')))
            ->assertForbidden();
    }

    public function test_expired_pending_is_cleared_and_user_redirected_with_status(): void
    {
        // Aunque la firma de la URL sea válida, si el pending_email_sent_at ya pasó la ventana
        // (60 min), no aplicamos el cambio y limpiamos el pending (no se queda colgado).
        $user = User::factory()->create([
            'email' => 'ana@example.com',
            'pending_email' => 'nueva@example.com',
            'pending_email_sent_at' => now()->subHours(2),
        ]);

        $this->get($this->confirmUrl($user))
            ->assertRedirect(route('account'))
            ->assertSessionHas('status', 'email-change-expired');

        $user->refresh();
        $this->assertSame('ana@example.com', $user->email);
        $this->assertNull($user->pending_email);
    }

    public function test_taken_pending_is_cleared_when_another_user_registered_it(): void
    {
        // Race real: Alice pide cambiar a "compartido@example.com", pero antes de confirmar,
        // Bob se registra con ese email. Al pulsar Alice el enlace, NO se aplica el cambio
        // y limpiamos el pending de Alice.
        $alice = User::factory()->create([
            'email' => 'alice@example.com',
            'pending_email' => 'compartido@example.com',
            'pending_email_sent_at' => now(),
        ]);
        User::factory()->create(['email' => 'compartido@example.com']); // Bob lo registra

        $this->get($this->confirmUrl($alice))
            ->assertRedirect(route('account'))
            ->assertSessionHas('status', 'email-change-taken');

        $alice->refresh();
        $this->assertSame('alice@example.com', $alice->email);
        $this->assertNull($alice->pending_email);
    }

    public function test_unknown_user_id_returns_403(): void
    {
        $url = URL::temporarySignedRoute('account.email.confirm', now()->addMinutes(60), [
            'id' => 999_999, 'hash' => sha1('cualquiera@example.com'),
        ]);

        $this->get($url)->assertForbidden();
    }

    public function test_unsigned_link_is_rejected(): void
    {
        $user = User::factory()->create([
            'email' => 'ana@example.com',
            'pending_email' => 'nueva@example.com',
            'pending_email_sent_at' => now(),
        ]);

        $this->get(route('account.email.confirm', [
            'id' => $user->id, 'hash' => sha1('nueva@example.com'),
        ]))->assertForbidden();
    }

    public function test_confirm_notifies_the_previous_email_for_anti_takeover(): void
    {
        // T2.3 / C-07 — al confirmar, el email VIEJO recibe un aviso del cambio (cierre del
        // loop anti-takeover). Si la víctima ve el mensaje en su buzón original, sabe que la
        // cuenta fue comprometida y puede contactar a tiempo.
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'ana@example.com',
            'pending_email' => 'nueva@example.com',
            'pending_email_sent_at' => now(),
        ]);

        $this->get($this->confirmUrl($user))->assertRedirect(route('account'));

        // La notif al viejo se rutea por `previous_email` (inyectado en runtime, no es atributo
        // persistido). Verificamos que se envió y que su routing apunta al email anterior.
        Notification::assertSentTo($user, EmailChangeCompleted::class, function ($notif, $channels, $notifiable) {
            return $notif->routeNotificationForMail($notifiable) === 'ana@example.com';
        });
    }
}
