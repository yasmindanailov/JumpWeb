<?php

namespace Tests\Feature\Account;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\AccountCredentials;
use App\Livewire\Account\LogoutOtherDevices;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 4.5a — "Cerrar sesión en los demás dispositivos": exige contraseña y, con
 * sesiones en BD, borra las demás sesiones del usuario conservando la actual.
 */
class LogoutOtherDevicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_the_current_password(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(LogoutOtherDevices::class)
            ->set('current_password', 'incorrecta')
            ->call('confirm')
            ->assertHasErrors('current_password');
    }

    public function test_succeeds_with_the_correct_password(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(LogoutOtherDevices::class)
            ->set('current_password', 'password')
            ->call('confirm')
            ->assertHasNoErrors()
            ->assertRedirect(route('account'));
    }

    public function test_purges_other_database_sessions_but_keeps_the_current_one(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(LogoutOtherDevices::class);
        $currentId = session()->getId();

        // Sesión actual (debe sobrevivir) + sesión de otro dispositivo (debe borrarse).
        DB::table('sessions')->insert([
            ['id' => $currentId, 'user_id' => $user->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'current', 'payload' => 'x', 'last_activity' => time()],
            ['id' => 'otra-sesion-id', 'user_id' => $user->id, 'ip_address' => '10.0.0.9', 'user_agent' => 'other', 'payload' => 'y', 'last_activity' => time()],
        ]);

        $component->set('current_password', 'password')->call('confirm')->assertHasNoErrors();

        $this->assertDatabaseHas('sessions', ['id' => $currentId]);
        $this->assertDatabaseMissing('sessions', ['id' => 'otra-sesion-id']);
    }

    /**
     * ⚠️ **El limitador que la web heredó del dominio, y su aviso EN PANTALLA** (tanda 2 · paso 8).
     *
     * El techo llegó en el paso 6a sin tocar la web (`DECISIONES #120(o)`), pero su mensaje va a la
     * clave `_global` —para no leerse como «esta contraseña está mal» bajo el input— y **esta vista
     * no pintaba ninguna clave global**: al agotar los cinco intentos, el formulario no hacía nada y
     * no decía nada. Es la familia de `#117`. Por eso el caso mira las DOS cosas: que el intento se
     * deniegue y que el titular pueda LEER por qué.
     */
    public function test_the_limiter_denies_the_sixth_attempt_and_the_screen_says_why(): void
    {
        // Reloj PARADO: el aviso lleva los segundos que quedan (`DECISIONES #64`).
        $this->freezeTime();

        $user = User::factory()->create();
        $component = Livewire::actingAs($user)->test(LogoutOtherDevices::class);

        for ($i = 0; $i < AccountCredentials::MAX_ATTEMPTS; $i++) {
            $component->set('current_password', 'mal-'.$i)->call('confirm')->assertHasErrors('current_password');
        }

        $component->set('current_password', 'password')->call('confirm')
            ->assertHasErrors('_global')
            ->assertSee(__('auth.throttle', ['seconds' => 60]));
    }
}
