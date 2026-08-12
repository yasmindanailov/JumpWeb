<?php

namespace Tests\Feature\Auth;

use App\Domain\Identity\Models\User;
use App\Livewire\Auth\ResetPassword;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Tests\TestCase;

class ResetPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // El control anti-filtración (HIBP) no debe llamar a la red en tests.
        $this->app->instance(UncompromisedVerifier::class, new class implements UncompromisedVerifier
        {
            public function verify($data): bool
            {
                return true;
            }
        });
    }

    public function test_reset_page_loads(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            ->assertSee(__('account.reset.title'));
    }

    public function test_password_is_reset_with_valid_token(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.com']);
        $token = Password::createToken($user);

        Livewire::test(ResetPassword::class, ['token' => $token, 'email' => 'ana@example.com'])
            ->set('password', 'una-frase-nueva-y-larga')
            ->set('password_confirmation', 'una-frase-nueva-y-larga')
            ->call('resetPassword')
            ->assertHasNoErrors()
            ->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('una-frase-nueva-y-larga', $user->fresh()->password));
    }

    public function test_invalid_token_is_rejected(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.com']);

        Livewire::test(ResetPassword::class, ['token' => 'token-invalido', 'email' => 'ana@example.com'])
            ->set('password', 'una-frase-nueva-y-larga')
            ->set('password_confirmation', 'una-frase-nueva-y-larga')
            ->call('resetPassword')
            ->assertHasErrors('email'); // mensaje del broker (passwords.token)

        $this->assertFalse(Hash::check('una-frase-nueva-y-larga', $user->fresh()->password));
    }

    public function test_reset_does_not_enumerate_users(): void
    {
        // Auditoría Fase 1 (A3): un email INEXISTENTE no debe dar un mensaje distinto al de un token
        // inválido (si difieren, el atacante distingue qué correos están registrados). Ambos muestran
        // el genérico `passwords.token`, NUNCA `passwords.user`.
        Livewire::test(ResetPassword::class, ['token' => 'token-invalido', 'email' => 'nadie@example.com'])
            ->set('password', 'una-frase-nueva-y-larga')
            ->set('password_confirmation', 'una-frase-nueva-y-larga')
            ->call('resetPassword')
            ->assertHasErrors('email')
            ->assertSee(__('passwords.token'))
            ->assertDontSee(__('passwords.user'));
    }

    public function test_password_must_be_confirmed_and_min_length(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.com']);
        $token = Password::createToken($user);

        Livewire::test(ResetPassword::class, ['token' => $token, 'email' => 'ana@example.com'])
            ->set('password', 'corta')
            ->set('password_confirmation', 'distinta')
            ->call('resetPassword')
            ->assertHasErrors('password');
    }

    public function test_reset_invalidates_all_existing_sessions_of_the_user(): void
    {
        // Caso de uso central: la víctima cambia password por sospecha de robo de sesión.
        // Tras el reset, NO debe quedar ninguna sesión activa del usuario.
        config(['session.driver' => 'database']);
        $user = User::factory()->create(['email' => 'ana@example.com']);
        $token = Password::createToken($user);

        // Simula 2 sesiones activas del usuario (otro navegador, otro móvil).
        DB::table('sessions')->insert([
            ['id' => 'sesion-a', 'user_id' => $user->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'a', 'payload' => 'x', 'last_activity' => time()],
            ['id' => 'sesion-b', 'user_id' => $user->id, 'ip_address' => '10.0.0.9', 'user_agent' => 'b', 'payload' => 'y', 'last_activity' => time()],
        ]);

        Livewire::test(ResetPassword::class, ['token' => $token, 'email' => 'ana@example.com'])
            ->set('password', 'una-frase-nueva-y-larga')
            ->set('password_confirmation', 'una-frase-nueva-y-larga')
            ->call('resetPassword')
            ->assertHasNoErrors();

        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
    }

    public function test_reset_rotates_the_remember_token(): void
    {
        // La rotación del remember_token invalida las cookies "recuérdame" de otros dispositivos.
        $user = User::factory()->create(['email' => 'ana@example.com']);
        $user->forceFill(['remember_token' => 'token-viejo'])->saveQuietly();
        $token = Password::createToken($user);

        Livewire::test(ResetPassword::class, ['token' => $token, 'email' => 'ana@example.com'])
            ->set('password', 'una-frase-nueva-y-larga')
            ->set('password_confirmation', 'una-frase-nueva-y-larga')
            ->call('resetPassword')
            ->assertHasNoErrors();

        $this->assertNotSame('token-viejo', $user->fresh()->remember_token);
    }

    public function test_expired_token_is_rejected(): void
    {
        // El broker valida la caducidad del token (60 min, config). Un token de hace 2 h debe fallar.
        $user = User::factory()->create(['email' => 'ana@example.com']);
        $token = Password::createToken($user);

        // Envejecemos el token: el broker mira `created_at` en password_reset_tokens.
        DB::table('password_reset_tokens')
            ->where('email', 'ana@example.com')
            ->update(['created_at' => now()->subHours(2)]);

        Livewire::test(ResetPassword::class, ['token' => $token, 'email' => 'ana@example.com'])
            ->set('password', 'una-frase-nueva-y-larga')
            ->set('password_confirmation', 'una-frase-nueva-y-larga')
            ->call('resetPassword')
            ->assertHasErrors('email');

        $this->assertFalse(Hash::check('una-frase-nueva-y-larga', $user->fresh()->password));
    }
}
