<?php

namespace Tests\Feature\Account;

use App\Domain\Identity\Models\User;
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
}
