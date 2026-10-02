<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\CustomerRegistrar;
use App\Domain\Platform\Models\AuditLog;
use App\Notifications\CustomerAccountCreated;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Fase 7.5 (#181) — Alta directa de cliente desde back-office (`CustomerRegistrar`).
 *
 * Sustituye a la invitación por enlace firmado: crea la cuenta verificada en el acto,
 * con rol customer + consent de privacidad, y le da la bienvenida por email. Sin contraseña desde
 * la A5c (`docs/specs/acceso-con-codigo.md` §4.12, `#869`): entra con su correo y un código.
 */
class CustomerRegistrarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_register_creates_verified_customer_with_consent_and_audits(): void
    {
        Notification::fake();

        $result = app(CustomerRegistrar::class)->register('Ada Lovelace', 'Ada@Example.com', '600111222');

        $this->assertTrue($result['created']);
        $user = $result['user'];
        $this->assertSame('ada@example.com', $user->email);   // normalizado
        $this->assertSame('Ada Lovelace', $user->name);
        $this->assertSame('600111222', $user->phone);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertTrue($user->hasRole('customer'));
        $this->assertNotNull($user->privacy_accepted_at);
        $this->assertSame(1, $user->consents()->where('type', 'privacy')->count());
        // No se firma waiver/terms en el alta directa (waiver se firma en puerta).
        $this->assertNull($user->waiver_accepted_at);

        Notification::assertSentTo($user, CustomerAccountCreated::class);

        $log = AuditLog::where('action', 'orders.customer_registered')->first();
        $this->assertNotNull($log);
        $this->assertSame((int) $user->id, (int) $log->target_id);
        $this->assertNull($log->payload); // logSensitive → solo sha256 del email en payload_hash
    }

    /**
     * Hasta la A5 el mostrador FABRICABA una contraseña y la mandaba en claro en la bienvenida (viajaba por la cola y
     * quedaba en la copia del registro de correos). Ahora la cuenta nace sin ninguna —con correo y sin él— y la
     * bienvenida dice cómo entrar: su correo y un código.
     */
    public function test_the_counter_account_is_born_without_a_password_and_its_welcome_says_how_to_enter(): void
    {
        Notification::fake();

        $conCorreo = app(CustomerRegistrar::class)->register('Bob', 'bob@example.com', null)['user'];
        $sinCorreo = app(CustomerRegistrar::class)->register('Eve', null, '600333444')['user'];

        $this->assertNull($conCorreo->fresh()?->getAttributes()['password'], 'la cuenta del mostrador nace sin contraseña');
        $this->assertNull($sinCorreo->fresh()?->getAttributes()['password'], 'también la de agenda, sin correo');

        Notification::assertSentTo($conCorreo, CustomerAccountCreated::class, function (CustomerAccountCreated $n) use ($conCorreo): bool {
            $correo = (string) $n->toMail($conCorreo)->render();

            return str_contains($correo, e(__('emails.customer_account_created.how_to_enter')))
                && str_contains($correo, 'bob@example.com');
        });
    }

    public function test_register_returns_existing_without_creating_or_emailing(): void
    {
        Notification::fake();
        $existing = User::factory()->create(['email' => 'taken@example.com']);
        $existing->roles()->sync([Role::where('name', 'customer')->value('id')]);

        $result = app(CustomerRegistrar::class)->register('Otro', 'Taken@Example.com', '600');

        $this->assertFalse($result['created']);
        $this->assertSame($existing->id, $result['user']->id);
        $this->assertSame(1, User::where('email', 'taken@example.com')->count());
        Notification::assertNothingSent();
    }
}
