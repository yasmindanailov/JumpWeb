<?php

namespace Tests\Feature\Account;

use App\Livewire\Account\UpdatePassword;
use App\Models\User;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 4.5a — Cambiar contraseña: exige la contraseña actual y confirma la nueva.
 */
class UpdatePasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // El chequeo anti-filtración (HIBP) no debe llamar a la red en tests.
        $this->app->instance(UncompromisedVerifier::class, new class implements UncompromisedVerifier
        {
            public function verify($data): bool
            {
                return true;
            }
        });
    }

    public function test_user_can_change_password_with_correct_current_password(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(UpdatePassword::class)
            ->set('current_password', 'password')
            ->set('password', 'una-frase-larga-y-unica-2026')
            ->set('password_confirmation', 'una-frase-larga-y-unica-2026')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('account'));

        $this->assertTrue(Hash::check('una-frase-larga-y-unica-2026', $user->fresh()->password));
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(UpdatePassword::class)
            ->set('current_password', 'incorrecta')
            ->set('password', 'una-frase-larga-y-unica-2026')
            ->set('password_confirmation', 'una-frase-larga-y-unica-2026')
            ->call('save')
            ->assertHasErrors('current_password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_new_password_must_be_confirmed(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(UpdatePassword::class)
            ->set('current_password', 'password')
            ->set('password', 'una-frase-larga-y-unica-2026')
            ->set('password_confirmation', 'no-coincide')
            ->call('save')
            ->assertHasErrors('password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_new_password_must_meet_minimum_length(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(UpdatePassword::class)
            ->set('current_password', 'password')
            ->set('password', 'corta')
            ->set('password_confirmation', 'corta')
            ->call('save')
            ->assertHasErrors('password');
    }

    public function test_changing_password_purges_other_sessions_keeping_the_current(): void
    {
        // Caso de uso central: el usuario cambia password por sospecha de robo. Tras el cambio,
        // su sesión actual sigue activa pero las DEMÁS deben quedar invalidadas (purgadas en BD).
        config(['session.driver' => 'database']);
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(UpdatePassword::class);
        $currentId = session()->getId();

        DB::table('sessions')->insert([
            ['id' => $currentId, 'user_id' => $user->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'current', 'payload' => 'x', 'last_activity' => time()],
            ['id' => 'otra-sesion', 'user_id' => $user->id, 'ip_address' => '10.0.0.9', 'user_agent' => 'other', 'payload' => 'y', 'last_activity' => time()],
        ]);

        $component
            ->set('current_password', 'password')
            ->set('password', 'una-frase-larga-y-unica-2026')
            ->set('password_confirmation', 'una-frase-larga-y-unica-2026')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('sessions', ['id' => $currentId]);
        $this->assertDatabaseMissing('sessions', ['id' => 'otra-sesion']);
    }
}
