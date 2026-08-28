<?php

namespace Tests\Feature\Admin\Users;

use App\Domain\Identity\Models\CustomerCard;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\CustomerCards;
use App\Domain\Platform\Models\AuditLog;
use App\Filament\Resources\Users\Pages\ViewUser;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 6 · subsistema A — la acción «Rotar carné QR» de `ViewUser` (`specs/identidad-qr-puerta.md`
 * §4.5, §9.6 B·5).
 *
 * Lo que se sostiene: quién la ve (solo clientes, nunca uno mismo, nunca anonimizada, con
 * `users.manage`), que rotar MATA el viejo en el acto y deja uno nuevo activo, que la auditoría
 * lleva al OPERADOR de actor y al titular de target —y nunca el token—, que sin carné previo emite
 * uno, y que el camino bloqueado entre render y submit no rota nada.
 */
class RotateCardActionTest extends TestCase
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

    public function test_rotate_card_visible_on_customer_for_admin(): void
    {
        Livewire::actingAs($this->userWithRole('admin'))
            ->test(ViewUser::class, ['record' => $this->userWithRole('customer')->id])
            ->assertActionVisible('rotateCard');
    }

    public function test_rotate_card_hidden_on_staff_admin_self_and_anonymized(): void
    {
        $admin = $this->userWithRole('admin');
        $anonymized = $this->userWithRole('customer');
        $anonymized->anonymize();

        foreach ([$this->userWithRole('staff'), $this->userWithRole('admin'), $admin, $anonymized] as $target) {
            Livewire::actingAs($admin)
                ->test(ViewUser::class, ['record' => $target->id])
                ->assertActionHidden('rotateCard');
        }
    }

    /**
     * `staff` no lleva `users.manage`: la operativa de puerta no incluye tocar credenciales. Y no es
     * que la acción se oculte — es que **la ficha entera le está vedada** (403 antes de que exista
     * ninguna acción; medido: `Livewire::test` ni siquiera monta la página). Se afirma el 403 real.
     */
    public function test_staff_cannot_even_open_the_page_where_the_action_lives(): void
    {
        $staff = $this->userWithRole('staff');

        $this->assertFalse($staff->hasPermission('users.manage'), 'la premisa del caso: staff no gestiona usuarios');

        $this->actingAs($staff)
            ->get(ViewUser::getUrl(['record' => $this->userWithRole('customer')->id]))
            ->assertForbidden();
    }

    public function test_rotating_kills_the_old_card_in_the_act_and_audits_the_operator(): void
    {
        $admin = $this->userWithRole('admin');
        $customer = $this->userWithRole('customer');
        $old = app(CustomerCards::class)->ensureFor($customer);
        $oldToken = (string) $old->plainToken();

        Livewire::actingAs($admin)
            ->test(ViewUser::class, ['record' => $customer->id])
            ->callAction('rotateCard')
            ->assertHasNoActionErrors()
            ->assertNotified(__('admin.users.actions.rotate_card.success'));

        $this->assertTrue($old->fresh()->isRevoked(), 'el viejo deja de valer en el acto');
        $this->assertSame(CustomerCard::REASON_ROTATED, $old->fresh()->revoked_reason);

        $new = app(CustomerCards::class)->activeFor($customer);
        $this->assertNotNull($new);
        $this->assertNotSame($oldToken, $new->plainToken());
        $this->assertSame(2, CustomerCard::where('user_id', $customer->id)->count(), 'historial: el viejo se conserva revocado');

        // La auditoría: el OPERADOR de actor, el titular de target — es lo que distingue esta rotación
        // de una hecha por el propio cliente— y nunca el token.
        $log = AuditLog::where('action', 'cards.rotated')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($customer->id, (int) $log->target_id);
        $this->assertSame($old->id, (int) $log->payload['card_id']);
        $this->assertStringNotContainsString($oldToken, json_encode(AuditLog::all()), 'RGPD-02: el token no se audita');
        $this->assertSame(0, AuditLog::where('action', 'users.rotate_card_blocked')->count());
    }

    public function test_rotating_a_customer_without_a_card_issues_the_first_one(): void
    {
        $admin = $this->userWithRole('admin');
        $customer = $this->userWithRole('customer');

        $this->assertSame(0, CustomerCard::count());

        Livewire::actingAs($admin)
            ->test(ViewUser::class, ['record' => $customer->id])
            ->callAction('rotateCard')
            ->assertHasNoActionErrors();

        $this->assertSame(1, CustomerCard::where('user_id', $customer->id)->count());
        $this->assertNotNull(app(CustomerCards::class)->activeFor($customer));
        $this->assertSame(1, AuditLog::where('action', 'cards.issued')->count());
        $this->assertSame(0, AuditLog::where('action', 'cards.rotated')->count(), 'no había nada que matar');
    }

    /**
     * El re-check entre render y submit: la cuenta se anonimiza con el modal abierto. Pase lo que pase
     * con el montaje de la acción, **el carné del cliente no se toca**.
     */
    public function test_a_change_between_render_and_submit_does_not_rotate(): void
    {
        $admin = $this->userWithRole('admin');
        $customer = $this->userWithRole('customer');
        $card = app(CustomerCards::class)->ensureFor($customer);

        $page = Livewire::actingAs($admin)->test(ViewUser::class, ['record' => $customer->id]);
        $page->assertActionVisible('rotateCard');

        $customer->anonymize();

        $page->callAction('rotateCard');

        $this->assertSame(CustomerCard::REASON_ANONYMIZED, $card->fresh()->revoked_reason, 'lo revocó anonymize(), no la acción');
        $this->assertSame(1, CustomerCard::where('user_id', $customer->id)->count(), 'la acción no emitió ningún carné');
        $this->assertSame(0, AuditLog::where('action', 'cards.rotated')->count());
    }
}
