<?php

namespace Tests\Feature\Admin\SpecialDates;

use App\Filament\Resources\SpecialDates\Pages\CreateSpecialDate;
use App\Filament\Resources\SpecialDates\Pages\EditSpecialDate;
use App\Filament\Resources\SpecialDates\SpecialDateResource;
use App\Models\AuditLog;
use App\Models\RateType;
use App\Models\Role;
use App\Models\SpecialDate;
use App\Models\User;
use App\Support\ParkSchedule;
use App\Support\RateResolver;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 7.7 (iter. 1) — Fechas especiales (`special_dates`): gating por `prices.manage`,
 * CRUD, coherencia de un día cerrado (sin ventana ni tarifa), validación cierre>apertura,
 * fecha única, auditoría e **integración end-to-end** con los consumidores en vivo
 * (`ParkSchedule` para apertura/cierre, `RateResolver` para la tarifa).
 */
class SpecialDateResourceTest extends TestCase
{
    use RefreshDatabase;

    private RateType $normal;

    private RateType $special;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->normal = RateType::create([
            'key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'],
            'is_special' => false, 'priority' => 0, 'is_active' => true,
        ]);
        $this->special = RateType::create([
            'key' => RateType::KEY_SPECIAL, 'label' => ['es' => 'Especial'],
            'is_special' => true, 'weekdays' => [0, 6], 'priority' => 10, 'is_active' => true,
        ]);
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

    private function customer(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'customer')->value('id')]);

        return $u;
    }

    // ─── Autorización ────────────────────────────────────────────────────────

    public function test_admin_can_view(): void
    {
        $this->actingAs($this->admin());
        $this->assertTrue(SpecialDateResource::canViewAny());
        $this->assertTrue(SpecialDateResource::shouldRegisterNavigation());
    }

    public function test_staff_is_denied(): void
    {
        $staff = $this->staff();
        $this->assertFalse($staff->hasPermission('prices.manage'));

        $this->actingAs($staff);
        $this->assertFalse(SpecialDateResource::canViewAny());
        $this->actingAs($staff)->get('/admin/special-dates')->assertForbidden();
    }

    public function test_customer_is_denied(): void
    {
        $this->actingAs($this->customer())->get('/admin/special-dates')->assertForbidden();
    }

    public function test_guest_is_redirected(): void
    {
        $this->get('/admin/special-dates')->assertRedirect();
    }

    // ─── Alta ────────────────────────────────────────────────────────────────

    public function test_create_open_day_with_window_and_rate(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateSpecialDate::class)
            ->fillForm([
                'date' => '2026-08-15',
                'note' => ['es' => 'Festivo'],
                'is_closed' => false,
                'open_time' => '12:00',
                'close_time' => '16:00',
                'rate_type_id' => $this->special->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $sd = SpecialDate::where('date', '2026-08-15')->firstOrFail();
        $this->assertFalse($sd->is_closed);
        $this->assertSame('12:00', substr((string) $sd->open_time, 0, 5));
        $this->assertSame('16:00', substr((string) $sd->close_time, 0, 5));
        $this->assertSame($this->special->id, $sd->rate_type_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'prices.special_date_created', 'target_id' => $sd->id]);
    }

    public function test_create_closed_day_defaults_and_clears_window_and_rate(): void
    {
        // Aunque lleguen ventana/tarifa, un día cerrado las anula (coherencia).
        Livewire::actingAs($this->admin())
            ->test(CreateSpecialDate::class)
            ->fillForm([
                'date' => '2026-09-01',
                'is_closed' => true,
                'open_time' => '12:00',
                'close_time' => '16:00',
                'rate_type_id' => $this->special->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $sd = SpecialDate::where('date', '2026-09-01')->firstOrFail();
        $this->assertTrue($sd->is_closed);
        $this->assertNull($sd->open_time);
        $this->assertNull($sd->close_time);
        $this->assertNull($sd->rate_type_id);
    }

    public function test_create_defaults_is_closed_false_when_untouched(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateSpecialDate::class)
            ->fillForm(['date' => '2026-10-12'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertFalse(SpecialDate::where('date', '2026-10-12')->firstOrFail()->is_closed);
    }

    public function test_create_rejects_duplicate_date(): void
    {
        SpecialDate::create(['date' => '2026-12-25', 'is_closed' => true]);

        Livewire::actingAs($this->admin())
            ->test(CreateSpecialDate::class)
            ->fillForm(['date' => '2026-12-25', 'is_closed' => true])
            ->call('create')
            ->assertHasFormErrors(['date']);

        $this->assertSame(1, SpecialDate::where('date', '2026-12-25')->count());
    }

    public function test_create_rejects_close_before_open(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateSpecialDate::class)
            ->fillForm([
                'date' => '2026-11-20',
                'is_closed' => false,
                'open_time' => '16:00',
                'close_time' => '10:00',
            ])
            ->call('create');

        // No se persiste con una ventana inválida (lo cace el `->after()` del form o la guarda del trait).
        $this->assertDatabaseMissing('special_dates', ['date' => '2026-11-20']);
    }

    // ─── Edición ──────────────────────────────────────────────────────────────

    public function test_edit_updates_and_audits_diff(): void
    {
        $sd = SpecialDate::create([
            'date' => '2026-08-15', 'is_closed' => false,
            'open_time' => '10:00:00', 'close_time' => '20:00:00', 'rate_type_id' => $this->special->id,
        ]);

        Livewire::actingAs($this->admin())
            ->test(EditSpecialDate::class, ['record' => $sd->id])
            ->fillForm(['close_time' => '18:00'])
            ->call('save')
            ->assertHasNoFormErrors();

        $sd->refresh();
        $this->assertSame('18:00', substr((string) $sd->close_time, 0, 5));
        $this->assertDatabaseHas('audit_logs', ['action' => 'prices.special_date_updated', 'target_id' => $sd->id]);
    }

    public function test_edit_marking_closed_clears_window_and_rate(): void
    {
        $sd = SpecialDate::create([
            'date' => '2026-08-15', 'is_closed' => false,
            'open_time' => '10:00:00', 'close_time' => '20:00:00', 'rate_type_id' => $this->special->id,
        ]);

        Livewire::actingAs($this->admin())
            ->test(EditSpecialDate::class, ['record' => $sd->id])
            ->fillForm(['is_closed' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $sd->refresh();
        $this->assertTrue($sd->is_closed);
        $this->assertNull($sd->open_time);
        $this->assertNull($sd->close_time);
        $this->assertNull($sd->rate_type_id);
    }

    public function test_delete_removes_and_audits(): void
    {
        $sd = SpecialDate::create(['date' => '2026-09-01', 'is_closed' => true]);

        Livewire::actingAs($this->admin())
            ->test(EditSpecialDate::class, ['record' => $sd->id])
            ->callAction('deleteSpecialDate');

        $this->assertDatabaseMissing('special_dates', ['id' => $sd->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'prices.special_date_deleted', 'target_id' => $sd->id]);
    }

    // ─── Integración end-to-end con los consumidores en vivo ───────────────────

    public function test_closed_day_blocks_via_park_schedule(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateSpecialDate::class)
            ->fillForm(['date' => '2026-12-25', 'is_closed' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        // El parque queda cerrado ese día (lo que bloquea compra pública + generación de franjas).
        $this->assertFalse(app(ParkSchedule::class)->isOpenOn(Carbon::parse('2026-12-25')));
    }

    public function test_open_day_window_applies_via_park_schedule(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateSpecialDate::class)
            ->fillForm([
                'date' => '2026-12-24', 'is_closed' => false,
                'open_time' => '10:00', 'close_time' => '14:00',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $hours = app(ParkSchedule::class)->effectiveFor(Carbon::parse('2026-12-24'));
        $this->assertTrue($hours['is_open']);
        $this->assertSame('10:00', substr((string) $hours['open'], 0, 5));
        $this->assertSame('14:00', substr((string) $hours['close'], 0, 5));
    }

    public function test_special_rate_applies_that_day_via_rate_resolver(): void
    {
        // Un martes (que sería tarifa normal) marcado como festivo con tarifa especial.
        $tuesday = Carbon::today()->next(Carbon::TUESDAY);

        Livewire::actingAs($this->admin())
            ->test(CreateSpecialDate::class)
            ->fillForm([
                'date' => $tuesday->toDateString(), 'is_closed' => false,
                'rate_type_id' => $this->special->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // RateResolver da prioridad a la fecha especial sobre la regla por día de la semana.
        $this->assertTrue($this->special->is(app(RateResolver::class)->for($tuesday)));
    }

    // ─── Hallazgos de la revisión adversarial ──────────────────────────────────

    public function test_create_open_day_with_only_open_time(): void
    {
        // Ventana parcial: solo apertura especial; el cierre se hereda del horario semanal.
        Livewire::actingAs($this->admin())
            ->test(CreateSpecialDate::class)
            ->fillForm([
                'date' => '2026-07-10', 'is_closed' => false, 'open_time' => '11:00',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $sd = SpecialDate::where('date', '2026-07-10')->firstOrFail();
        $this->assertSame('11:00', substr((string) $sd->open_time, 0, 5));
        $this->assertNull($sd->close_time);

        $hours = app(ParkSchedule::class)->effectiveFor(Carbon::parse('2026-07-10'));
        $this->assertTrue($hours['is_open']);
        $this->assertSame('11:00', substr((string) $hours['open'], 0, 5));
    }

    public function test_edit_note_only_does_not_record_spurious_time_changes(): void
    {
        // El TimePicker entrega 'HH:MM' y la BD lee 'HH:MM:SS': una edición que NO toca la hora
        // no debe registrar un cambio falso de open_time/close_time en la auditoría.
        $sd = SpecialDate::create([
            'date' => '2026-08-15', 'is_closed' => false,
            'open_time' => '10:00:00', 'close_time' => '20:00:00',
            'note' => ['es' => 'Original'],
        ]);

        Livewire::actingAs($this->admin())
            ->test(EditSpecialDate::class, ['record' => $sd->id])
            ->fillForm(['note' => ['es' => 'Cambiada']])
            ->call('save')
            ->assertHasNoFormErrors();

        $log = AuditLog::where('action', 'prices.special_date_updated')
            ->where('target_id', $sd->id)->latest('id')->first();
        $this->assertNotNull($log);
        $changed = $log->payload['changed'] ?? [];
        $this->assertArrayNotHasKey('open_time', $changed);
        $this->assertArrayNotHasKey('close_time', $changed);
        $this->assertContains('note', $log->payload['texts_changed'] ?? []);
    }

    public function test_edit_rejects_changing_date_to_a_duplicate(): void
    {
        $a = SpecialDate::create(['date' => '2026-08-15', 'is_closed' => true]);
        SpecialDate::create(['date' => '2026-09-01', 'is_closed' => true]);

        Livewire::actingAs($this->admin())
            ->test(EditSpecialDate::class, ['record' => $a->id])
            ->fillForm(['date' => '2026-09-01'])
            ->call('save')
            ->assertHasFormErrors(['date']);

        $this->assertSame('2026-08-15', $a->refresh()->date->toDateString());
    }

    public function test_edit_rejects_close_before_open(): void
    {
        $sd = SpecialDate::create([
            'date' => '2026-08-15', 'is_closed' => false,
            'open_time' => '10:00:00', 'close_time' => '20:00:00',
        ]);

        Livewire::actingAs($this->admin())
            ->test(EditSpecialDate::class, ['record' => $sd->id])
            ->fillForm(['open_time' => '21:00']) // 21:00 > 20:00 → ventana inválida
            ->call('save');

        // No se aplica el cambio inválido (lo cace el ->after() o la guarda del trait).
        $this->assertSame('10:00:00', (string) $sd->refresh()->open_time);
    }

    public function test_edit_with_inactive_referenced_rate_can_still_be_edited(): void
    {
        // Una fecha referencia una tarifa que LUEGO se desactiva (forma de retirarla en 7.8):
        // debe poder seguir editándose (p. ej. la nota) sin perder la tarifa ni quedar bloqueada.
        $sd = SpecialDate::create([
            'date' => '2026-08-15', 'is_closed' => false,
            'rate_type_id' => $this->special->id, 'note' => ['es' => 'Festivo'],
        ]);
        $this->special->update(['is_active' => false]);

        Livewire::actingAs($this->admin())
            ->test(EditSpecialDate::class, ['record' => $sd->id])
            ->fillForm(['note' => ['es' => 'Festivo (revisado)']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($this->special->id, $sd->refresh()->rate_type_id);
    }
}
