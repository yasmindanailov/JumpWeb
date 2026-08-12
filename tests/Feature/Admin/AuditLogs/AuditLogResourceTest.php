<?php

namespace Tests\Feature\Admin\AuditLogs;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\AuditLog;
use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Visibilidad de incidencias (recomendación C, 2026-06-15) — `AuditLogResource` (página
 * «Incidencias»): gating por `audit.view`, solo lectura (sin crear/editar/borrar) y filtro por
 * defecto a las acciones críticas.
 */
class AuditLogResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'admin')->value('id')]);

        return $u;
    }

    private function staff(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'staff')->value('id')]);

        return $u;
    }

    private function grantAuditViewToStaff(): void
    {
        Role::where('name', 'staff')->first()
            ->permissions()->syncWithoutDetaching(Permission::where('name', 'audit.view')->value('id'));
    }

    /** Crea una fila de audit_logs (payload_hash es NOT NULL, como lo escribe AuditLogger). */
    private function log(string $action, ?\DateTimeInterface $when = null): AuditLog
    {
        return AuditLog::create([
            'action' => $action,
            'payload_hash' => hash('sha256', $action),
            'created_at' => $when ?? now(),
        ]);
    }

    public function test_admin_can_access_and_default_staff_cannot(): void
    {
        // `audit.view` NO está en STAFF_DEFAULT_PERMISSIONS → el empleado normal no la ve.
        $this->actingAs($this->admin())->get('/admin/incidencias')->assertOk();
        $this->actingAs($this->staff())->get('/admin/incidencias')->assertForbidden();
    }

    public function test_staff_with_audit_view_can_access(): void
    {
        $staff = $this->staff();
        $this->grantAuditViewToStaff();

        $this->actingAs($staff)->get('/admin/incidencias')->assertOk();
    }

    public function test_resource_is_read_only(): void
    {
        $log = $this->log(AuditLog::ACTION_DUPLICATE_CAPTURE);

        $this->assertFalse(AuditLogResource::canCreate());
        $this->assertFalse(AuditLogResource::canEdit($log));
        $this->assertFalse(AuditLogResource::canDelete($log));
        // Solo expone la página índice (sin create/edit/view).
        $this->assertSame(['index'], array_keys(AuditLogResource::getPages()));
    }

    public function test_default_filter_shows_only_critical_incidents(): void
    {
        $critical = $this->log(AuditLog::ACTION_DUPLICATE_CAPTURE);
        $routine = $this->log('catalog.updated');

        Livewire::actingAs($this->admin())
            ->test(ListAuditLogs::class)
            ->assertCanSeeTableRecords([$critical])
            ->assertCanNotSeeTableRecords([$routine]);
    }

    public function test_incident_on_an_order_links_to_it_by_code_not_id(): void
    {
        // Regresión: OrderResource resuelve el binding por `code` (#130), no por `id`. El enlace de
        // la fila debe construirse con el modelo (→ getRouteKey()=code); con el id entero saldría
        // /admin/orders/{id} y daría 404.
        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'JJ-INCID9', 'status' => Order::STATUS_PAID,
            'subtotal' => 1000, 'total' => 1000, 'currency' => 'EUR',
        ]);
        AuditLog::create([
            'action' => AuditLog::ACTION_DUPLICATE_CAPTURE,
            'target_type' => (new Order)->getMorphClass(),
            'target_id' => $order->id,
            'payload_hash' => hash('sha256', 'x'),
            'created_at' => now(),
        ]);

        $expectedUrl = OrderResource::getUrl('view', ['record' => $order]); // por code

        Livewire::actingAs($this->admin())
            ->test(ListAuditLogs::class)
            ->assertSee('Pedido '.$order->code)   // la etiqueta muestra el código
            ->assertSee($expectedUrl, escape: false); // la fila enlaza por code, no por id
    }

    public function test_navigation_badge_counts_recent_critical_incidents(): void
    {
        $this->log(AuditLog::ACTION_DUPLICATE_CAPTURE);
        $this->log(AuditLog::ACTION_OVERBOOKED_CAPTURE, now()->subDays(2));
        $this->log(AuditLog::ACTION_DUPLICATE_CAPTURE, now()->subDays(10)); // fuera de 7d
        $this->log('catalog.updated'); // no crítico

        $this->assertSame('2', AuditLogResource::getNavigationBadge());
    }
}
