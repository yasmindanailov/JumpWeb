<?php

namespace Tests\Feature\Admin\Users;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\AuditLog;
use App\Filament\Resources\Users\Pages\ViewUser;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 7.5 — Acción "Enviar enlace de contraseña" de `ViewUser` (decisión #180).
 *
 * Reutiliza el broker estándar de Laravel (`Password::sendResetLink`), que despacha
 * la notificación NATIVA `ResetPassword` SOBRE el usuario — por eso el test usa
 * `Notification::fake()` (no `Mail::fake()`). Audit `logSensitive` (payload null).
 */
class SendPasswordResetActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', $role)->value('id')]);

        return $u;
    }

    public function test_send_reset_visible_on_customer(): void
    {
        Livewire::actingAs($this->userWithRole('admin'))
            ->test(ViewUser::class, ['record' => $this->userWithRole('customer')->id])
            ->assertActionVisible('sendPasswordReset');
    }

    public function test_send_reset_hidden_on_staff_target(): void
    {
        Livewire::actingAs($this->userWithRole('admin'))
            ->test(ViewUser::class, ['record' => $this->userWithRole('staff')->id])
            ->assertActionHidden('sendPasswordReset');
    }

    public function test_send_reset_hidden_on_anonymized(): void
    {
        $customer = $this->userWithRole('customer');
        $customer->anonymize();

        Livewire::actingAs($this->userWithRole('admin'))
            ->test(ViewUser::class, ['record' => $customer->id])
            ->assertActionHidden('sendPasswordReset');
    }

    public function test_send_reset_dispatches_notification_and_audits(): void
    {
        Notification::fake();
        $admin = $this->userWithRole('admin');
        $customer = $this->userWithRole('customer');

        Livewire::actingAs($admin)
            ->test(ViewUser::class, ['record' => $customer->id])
            ->callAction('sendPasswordReset')
            ->assertHasNoActionErrors();

        Notification::assertSentTo($customer, ResetPassword::class);

        $log = AuditLog::where('action', 'users.password_reset_sent')->latest()->first();
        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($customer->id, (int) $log->target_id);
        // logSensitive → payload null (el email solo va como sha256 en payload_hash).
        $this->assertNull($log->payload);
    }
}
