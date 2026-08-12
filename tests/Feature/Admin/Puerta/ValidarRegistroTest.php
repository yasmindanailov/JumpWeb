<?php

namespace Tests\Feature\Admin\Puerta;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Models\Setting;
use App\Livewire\Admin\Puerta\ValidarRegistro;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 7.1a — Página "Validar registro" en puerta (decisiones #119 y #126).
 *
 * Cubre privacy-by-design (RGPD #19), permisos, 3 estados de resultado,
 * detección email/phone con normalización, rate limit configurable, audit log
 * con sha256 y aislamiento de datos personales en la respuesta.
 */
class ValidarRegistroTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function staff(?string $panelLocale = null): User
    {
        $u = User::factory()->create(['panel_locale' => $panelLocale]);
        $u->roles()->sync([Role::where('name', 'staff')->value('id')]);

        return $u;
    }

    // ─── Auth + permisos ──────────────────────────────────────────────────

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.puerta.validar'))->assertRedirect(route('login'));
    }

    public function test_customer_role_gets_403(): void
    {
        $customer = User::factory()->create();
        $customer->roles()->sync([Role::where('name', 'customer')->value('id')]);

        $this->actingAs($customer)->get(route('admin.puerta.validar'))->assertForbidden();
    }

    public function test_staff_with_permission_can_load_page(): void
    {
        $this->actingAs($this->staff())
            ->get(route('admin.puerta.validar'))
            ->assertOk()
            ->assertSeeText('Validar registro');
    }

    public function test_staff_without_specific_permission_gets_403(): void
    {
        // Le quitamos al staff el permiso `registrations.validate` para probar el guard del mount.
        $perm = Permission::where('name', 'registrations.validate')->value('id');
        $staff = $this->staff();
        $staff->roles->first()->permissions()->detach($perm);

        $this->actingAs($staff)->get(route('admin.puerta.validar'))->assertForbidden();
    }

    public function test_action_reauthorizes_after_live_permission_revocation(): void
    {
        // Auditoría Fase 1 (A8): `mount()` autoriza UNA vez. Si al staff se le revoca el permiso EN VIVO
        // (pestaña abierta), la siguiente acción Livewire (`search`) debe dar 403 —no ejecutarse— porque
        // ahora se re-autoriza en cada acción. `hasPermission` lee el pivote fresco, así que basta volver
        // a comprobarlo.
        $staff = $this->staff();
        $component = Livewire::actingAs($staff)->test(ValidarRegistro::class); // mount() OK

        $perm = Permission::where('name', 'registrations.validate')->value('id');
        $staff->roles->first()->permissions()->detach($perm); // revocación EN VIVO

        $component->set('input', 'ana@example.com')->call('search')->assertStatus(403);
    }

    // ─── 3 estados de resultado ──────────────────────────────────────────

    public function test_state_registered_with_waiver_returns_status_and_date(): void
    {
        $cliente = User::factory()->create([
            'email' => 'cliente@example.com',
            'waiver_accepted_at' => now()->subDays(3)->setTime(10, 0),
        ]);

        Livewire::actingAs($this->staff())
            ->test(ValidarRegistro::class)
            ->set('input', 'cliente@example.com')
            ->call('search')
            ->assertSet('result.status', ValidarRegistro::STATUS_REGISTERED_WITH_WAIVER)
            ->assertSee($cliente->waiver_accepted_at->setTimezone('Europe/Madrid')->format('d/m/Y'));
    }

    public function test_state_registered_no_waiver(): void
    {
        User::factory()->create([
            'email' => 'sinwaiver@example.com',
            'waiver_accepted_at' => null,
        ]);

        Livewire::actingAs($this->staff())
            ->test(ValidarRegistro::class)
            ->set('input', 'sinwaiver@example.com')
            ->call('search')
            ->assertSet('result.status', ValidarRegistro::STATUS_REGISTERED_NO_WAIVER);
    }

    public function test_waiver_check_disabled_collapses_to_two_states(): void
    {
        // #216: con la comprobación de waiver DESACTIVADA, un cliente registrado (firme o no) sale
        // como STATUS_REGISTERED (2 estados), sin mirar el waiver (lo gestiona el sistema externo).
        Setting::updateOrCreate(['key' => 'puerta.waiver_check_enabled'], ['value' => '0', 'group' => 'puerta']);

        User::factory()->create(['email' => 'sinwaiver@example.com', 'waiver_accepted_at' => null]);

        Livewire::actingAs($this->staff())
            ->test(ValidarRegistro::class)
            ->set('input', 'sinwaiver@example.com')
            ->call('search')
            ->assertSet('result.status', ValidarRegistro::STATUS_REGISTERED);
    }

    public function test_waiver_check_enabled_by_default_keeps_three_states(): void
    {
        // Sin el ajuste, el default es ON → sigue distinguiendo "sin waiver".
        User::factory()->create(['email' => 'sinwaiver@example.com', 'waiver_accepted_at' => null]);

        Livewire::actingAs($this->staff())
            ->test(ValidarRegistro::class)
            ->set('input', 'sinwaiver@example.com')
            ->call('search')
            ->assertSet('result.status', ValidarRegistro::STATUS_REGISTERED_NO_WAIVER);
    }

    public function test_state_not_registered(): void
    {
        Livewire::actingAs($this->staff())
            ->test(ValidarRegistro::class)
            ->set('input', 'desconocido@example.com')
            ->call('search')
            ->assertSet('result.status', ValidarRegistro::STATUS_NOT_REGISTERED);
    }

    // ─── Detección email vs phone + normalización ───────────────────────

    public function test_detect_input_type(): void
    {
        $this->assertSame('email', ValidarRegistro::detectInputType('foo@bar.com'));
        $this->assertSame('phone', ValidarRegistro::detectInputType('+34 600 11 22 33'));
        $this->assertSame('phone', ValidarRegistro::detectInputType('600112233'));
        $this->assertSame('phone', ValidarRegistro::detectInputType('(600) 11-22.33'));
        $this->assertNull(ValidarRegistro::detectInputType(''));
        $this->assertNull(ValidarRegistro::detectInputType('not-an-email-no-at'));
        $this->assertNull(ValidarRegistro::detectInputType('abc@'));
        $this->assertNull(ValidarRegistro::detectInputType('letras y numeros 123'));
    }

    public function test_phone_search_matches_regardless_of_format(): void
    {
        User::factory()->create([
            'phone' => '+34 600 11 22 33',
            'waiver_accepted_at' => now(),
        ]);

        // 4 formatos del MISMO número (con código de país) deben encontrar el user.
        foreach ([
            '+34 600 11 22 33',
            '34 600 11 22 33',
            '+34-600-11-22-33',
            '34600112233',
        ] as $variant) {
            Livewire::actingAs($this->staff())
                ->test(ValidarRegistro::class)
                ->set('input', $variant)
                ->call('search')
                ->assertSet('result.status', ValidarRegistro::STATUS_REGISTERED_WITH_WAIVER,
                    "Variante '{$variant}' debería matchear con '+34 600 11 22 33'.");
        }

        // En cambio, sin código de país NO matchea (son números distintos):
        Livewire::actingAs($this->staff())
            ->test(ValidarRegistro::class)
            ->set('input', '600 11 22 33')
            ->call('search')
            ->assertSet('result.status', ValidarRegistro::STATUS_NOT_REGISTERED,
                'Sin código de país no debe matchear: 600112233 ≠ 34600112233.');
    }

    public function test_invalid_input_returns_invalid_status(): void
    {
        Livewire::actingAs($this->staff())
            ->test(ValidarRegistro::class)
            ->set('input', 'no es nada válido')
            ->call('search')
            ->assertSet('result.status', ValidarRegistro::STATUS_INVALID_INPUT);
    }

    // ─── Privacy by design (RGPD) ────────────────────────────────────────

    public function test_response_never_exposes_user_name_or_other_pii(): void
    {
        User::factory()->create([
            'name' => 'Juan Sensible',
            'email' => 'juan@example.com',
            'phone' => '+34600999888',
            'waiver_accepted_at' => now(),
        ]);

        Livewire::actingAs($this->staff())
            ->test(ValidarRegistro::class)
            ->set('input', 'juan@example.com')
            ->call('search')
            ->assertSet('result.status', ValidarRegistro::STATUS_REGISTERED_WITH_WAIVER)
            ->assertDontSee('Juan Sensible')
            ->assertDontSee('600999888');
    }

    // ─── Audit log (sha256, NO en claro) ──────────────────────────────────

    public function test_audit_log_uses_sha256_never_stores_raw_identifier(): void
    {
        $email = 'auditado@example.com';
        User::factory()->create(['email' => $email, 'waiver_accepted_at' => now()]);

        Livewire::actingAs($this->staff())
            ->test(ValidarRegistro::class)
            ->set('input', $email)
            ->call('search');

        $log = AuditLog::where('action', 'registrations.validated')->latest()->first();
        $this->assertNotNull($log);
        $this->assertNull($log->payload, 'Acciones con dato personal NO guardan payload claro.');
        $this->assertSame(hash('sha256', $email), $log->payload_hash);

        // Defense-in-depth: el email NO debe estar en NINGUNA columna en claro.
        $this->assertStringNotContainsString(
            $email,
            json_encode($log->fresh()->getAttributes()),
            'El identificador buscado JAMÁS debe persistirse en claro.',
        );
    }

    // ─── Rate limit configurable ──────────────────────────────────────────

    public function test_rate_limit_uses_configurable_setting(): void
    {
        // Configuro un límite bajo (3/min) para poder dispararlo en el test.
        Setting::updateOrCreate(
            ['key' => 'puerta.validate_rate_limit_per_minute'],
            ['value' => '3', 'group' => 'puerta'],
        );

        $staff = $this->staff();
        // Limpio cualquier rate limit cacheado de tests previos.
        RateLimiter::clear("puerta:validate:user:{$staff->id}");

        // 3 intentos pasan…
        for ($i = 0; $i < 3; $i++) {
            Livewire::actingAs($staff)
                ->test(ValidarRegistro::class)
                ->set('input', 'foo@bar.com')
                ->call('search')
                ->assertSet('result.status', ValidarRegistro::STATUS_NOT_REGISTERED);
        }

        // El 4º dispara rate limit.
        Livewire::actingAs($staff)
            ->test(ValidarRegistro::class)
            ->set('input', 'foo@bar.com')
            ->call('search')
            ->assertSet('result.status', ValidarRegistro::STATUS_RATE_LIMITED);
    }

    public function test_rate_limit_rejection_is_audited_once_per_window(): void
    {
        // Auditoría Fase 1 (Sistema 5): el RECHAZO por rate-limit deja rastro estructurado en
        // `audit_logs` (#127/#128) — es el evento anti-enumeración que conviene registrar — pero
        // SOLO una vez por ventana (2.º limitador) para no inflar el log (NO Prunable). Sin PII.
        Setting::updateOrCreate(
            ['key' => 'puerta.validate_rate_limit_per_minute'],
            ['value' => '2', 'group' => 'puerta'],
        );
        $staff = $this->staff();
        RateLimiter::clear("puerta:validate:user:{$staff->id}");
        RateLimiter::clear("puerta:validate:audit:user:{$staff->id}");

        // 2 pasan, los 3 siguientes son rechazos (5 búsquedas en total).
        for ($i = 0; $i < 5; $i++) {
            Livewire::actingAs($staff)
                ->test(ValidarRegistro::class)
                ->set('input', 'foo@bar.com')
                ->call('search');
        }

        $audits = AuditLog::where('action', 'registrations.validate_rate_limited')->get();
        $this->assertCount(1, $audits, 'El rechazo se audita UNA sola vez por ventana.');
        $this->assertSame($staff->id, $audits->first()->user_id);
        $this->assertSame(2, $audits->first()->payload['limit']);
        // Sin PII: el identificador buscado NUNCA aparece en el payload del rechazo.
        $this->assertStringNotContainsString('foo@bar.com', json_encode($audits->first()->payload));
    }

    public function test_rate_limit_is_per_user_not_per_ip(): void
    {
        Setting::updateOrCreate(
            ['key' => 'puerta.validate_rate_limit_per_minute'],
            ['value' => '2', 'group' => 'puerta'],
        );

        $staffA = $this->staff();
        $staffB = $this->staff();
        RateLimiter::clear("puerta:validate:user:{$staffA->id}");
        RateLimiter::clear("puerta:validate:user:{$staffB->id}");

        // staff A agota su quota.
        for ($i = 0; $i < 2; $i++) {
            Livewire::actingAs($staffA)
                ->test(ValidarRegistro::class)
                ->set('input', 'x@y.com')
                ->call('search');
        }
        Livewire::actingAs($staffA)
            ->test(ValidarRegistro::class)
            ->set('input', 'x@y.com')
            ->call('search')
            ->assertSet('result.status', ValidarRegistro::STATUS_RATE_LIMITED);

        // staff B sigue pudiendo (su contador es independiente).
        Livewire::actingAs($staffB)
            ->test(ValidarRegistro::class)
            ->set('input', 'x@y.com')
            ->call('search')
            ->assertSet('result.status', ValidarRegistro::STATUS_NOT_REGISTERED);
    }

    // ─── i18n: ES y ZH_CN ────────────────────────────────────────────────

    public function test_page_renders_in_zh_when_panel_locale_is_zh(): void
    {
        $staff = $this->staff('zh_CN');

        $this->actingAs($staff)
            ->get(route('admin.puerta.validar'))
            ->assertOk()
            ->assertSeeText('验证注册')     // título
            ->assertSeeText('电子邮件或电话') // placeholder
            ->assertDontSeeText('Validar registro');
    }

    public function test_clear_resets_state(): void
    {
        Livewire::actingAs($this->staff())
            ->test(ValidarRegistro::class)
            ->set('input', 'foo@bar.com')
            ->call('search')
            ->assertSet('result.status', ValidarRegistro::STATUS_NOT_REGISTERED)
            ->call('clear')
            ->assertSet('input', '')
            ->assertSet('result', null);
    }

    // ─── UX: el query buscado se devuelve con el resultado ───────────────
    // Evita el caso "empleado teclea otra búsqueda sin pulsar Buscar y confunde
    // el resultado anterior con la nueva consulta".

    public function test_result_includes_query_for_registered_with_waiver(): void
    {
        $email = 'cliente@example.com';
        User::factory()->create(['email' => $email, 'waiver_accepted_at' => now()]);

        Livewire::actingAs($this->staff())
            ->test(ValidarRegistro::class)
            ->set('input', $email)
            ->call('search')
            ->assertSet('result.status', ValidarRegistro::STATUS_REGISTERED_WITH_WAIVER)
            ->assertSet('result.query', $email)
            ->assertSee($email);
    }

    public function test_result_includes_query_for_registered_no_waiver(): void
    {
        $email = 'sinwaiver@example.com';
        User::factory()->create(['email' => $email, 'waiver_accepted_at' => null]);

        Livewire::actingAs($this->staff())
            ->test(ValidarRegistro::class)
            ->set('input', $email)
            ->call('search')
            ->assertSet('result.query', $email)
            ->assertSee($email);
    }

    public function test_result_includes_query_for_not_registered(): void
    {
        $email = 'desconocido@example.com';

        Livewire::actingAs($this->staff())
            ->test(ValidarRegistro::class)
            ->set('input', $email)
            ->call('search')
            ->assertSet('result.query', $email)
            ->assertSee($email);
    }

    public function test_result_includes_query_for_phone_searches(): void
    {
        $phone = '+34 600 11 22 33';
        User::factory()->create(['phone' => $phone, 'waiver_accepted_at' => now()]);

        Livewire::actingAs($this->staff())
            ->test(ValidarRegistro::class)
            ->set('input', '34600112233')   // formato distinto al guardado
            ->call('search')
            ->assertSet('result.status', ValidarRegistro::STATUS_REGISTERED_WITH_WAIVER)
            ->assertSet('result.query', '34600112233')   // se devuelve lo que tecleó el empleado
            ->assertSee('34600112233');
    }
}
