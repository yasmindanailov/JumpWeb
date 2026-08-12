<?php

namespace Tests\Feature\Auth;

use App\Domain\Identity\Models\User;
use App\Livewire\Auth\ForgotPassword;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('forgot:127.0.0.1');
    }

    public function test_sends_reset_link_for_existing_user(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'ana@example.com']);

        Livewire::test(ForgotPassword::class)
            ->set('email', 'ana@example.com')
            ->call('sendLink')
            ->assertHasNoErrors()
            ->assertSet('sent', true);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_unknown_email_shows_same_generic_result(): void
    {
        Notification::fake();

        Livewire::test(ForgotPassword::class)
            ->set('email', 'desconocido@example.com')
            ->call('sendLink')
            ->assertHasNoErrors()
            ->assertSet('sent', true); // mismo resultado: no revela si el email existe

        Notification::assertNothingSent();
    }

    public function test_email_is_required_and_valid(): void
    {
        Livewire::test(ForgotPassword::class)
            ->set('email', 'no-es-email')
            ->call('sendLink')
            ->assertHasErrors('email')
            ->assertSet('sent', false);
    }

    public function test_is_rate_limited(): void
    {
        Notification::fake();

        for ($i = 0; $i < 5; $i++) {
            Livewire::test(ForgotPassword::class)
                ->set('email', "user{$i}@example.com")
                ->call('sendLink');
        }

        Livewire::test(ForgotPassword::class)
            ->set('email', 'user6@example.com')
            ->call('sendLink')
            ->assertHasErrors('email');
    }
}
