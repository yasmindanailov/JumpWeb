<?php

namespace Tests\Feature\Puerta;

use App\Domain\Identity\Models\CustomerVisit;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\GateVisits;
use App\Domain\Platform\Models\AnalyticsEvent;
use App\Domain\Platform\Models\AuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 6 · subsistema A — la VISITA ACREDITADA (`docs/specs/identidad-qr-puerta.md` §8.3, §9.2 A·4):
 * un acto explícito, idempotente por (cliente, día), auditado solo cuando escribe.
 */
class GateVisitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visit_is_registered_once_per_customer_and_day_and_audited_only_when_written(): void
    {
        $customer = User::factory()->create();
        $operator = User::factory()->create();
        $today = CarbonImmutable::parse('2026-09-05');
        $visits = app(GateVisits::class);
        $this->actingAs($operator);

        $this->assertFalse($visits->registeredOn($customer, $today));
        $this->assertTrue($visits->register($customer, $operator, $today), 'la primera vez escribe');
        $this->assertFalse($visits->register($customer, $operator, $today), 'la segunda vez NO: el saldo no depende de cuántas veces mire el empleado');
        $this->assertFalse($visits->register($customer, User::factory()->create(), $today), 'ni con otro operador');

        $this->assertTrue($visits->registeredOn($customer, $today));
        $this->assertSame(1, CustomerVisit::where('user_id', $customer->id)->count());
        $visit = CustomerVisit::where('user_id', $customer->id)->sole();
        $this->assertSame('2026-09-05', $visit->visited_on->toDateString());
        $this->assertSame($operator->id, (int) $visit->registered_by);

        $audit = AuditLog::where('action', 'puerta.visit_registered')->sole();
        $this->assertSame($operator->id, (int) $audit->user_id);
        $this->assertSame($customer->id, (int) $audit->target_id);
        $this->assertSame(['visited_on' => '2026-09-05'], $audit->payload);

        // Otro día es otra visita.
        $this->assertTrue($visits->register($customer, $operator, $today->addDay()));
        $this->assertSame(2, CustomerVisit::where('user_id', $customer->id)->count());
    }

    /**
     * T2b de la analítica (`specs/analitica.md` §4.2): la visita acreditada es un hecho del libro, y se anota
     * UNA vez por (cliente, día), como la fila — la segunda pulsación no escribe fila ni hecho.
     */
    public function test_a_written_visit_is_also_a_fact_of_the_analytics_ledger_once(): void
    {
        $customer = User::factory()->create();
        $operator = User::factory()->create();
        $today = CarbonImmutable::parse('2026-09-05');
        $this->actingAs($operator);

        app(GateVisits::class)->register($customer, $operator, $today);
        app(GateVisits::class)->register($customer, $operator, $today);

        $facts = AnalyticsEvent::query()->where('name', 'visit_checked_in')->get();
        $this->assertCount(1, $facts);
        $this->assertSame($customer->id, (int) $facts->first()->user_id);

        app(GateVisits::class)->register($customer, $operator, $today->addDay());
        $this->assertSame(2, AnalyticsEvent::query()->where('name', 'visit_checked_in')->count(), 'otro día, otro hecho');
    }

    public function test_the_visit_survives_the_operator_and_falls_with_the_customer(): void
    {
        $customer = User::factory()->create();
        $operator = User::factory()->create();
        $today = CarbonImmutable::parse('2026-09-05');
        app(GateVisits::class)->register($customer, $operator, $today);

        $operator->delete();
        $this->assertNull(CustomerVisit::where('user_id', $customer->id)->sole()->registered_by, 'la visita sobrevive al operador');

        $customer->delete();
        $this->assertSame(0, CustomerVisit::count(), 'sin titular la visita no vale puntos a nadie');
    }
}
