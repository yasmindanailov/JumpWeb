<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_guest_hitting_logout_is_redirected_to_login(): void
    {
        // El middleware `auth` envía a la ruta `login` (que abre el modal sobre la home).
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
