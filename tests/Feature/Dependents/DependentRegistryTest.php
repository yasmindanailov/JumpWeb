<?php

namespace Tests\Feature\Dependents;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Contracts\DependentRemoval;
use App\Domain\Identity\Exceptions\DependentHasReferencesException;
use App\Domain\Identity\Exceptions\DependentNotFoundException;
use App\Domain\Identity\Exceptions\DependentNotMinorException;
use App\Domain\Identity\Exceptions\DependentsLimitReachedException;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\DependentAssignment;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Identity\Services\DependentRegistry;
use App\Domain\Identity\Services\DependentSettings;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Identity\Services\WaiverSigner;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

/**
 * Fase 6 · menores a cargo — el registro de personas a cargo (`docs/specs/menores-a-cargo.md`
 * §4.1, §4.2, §4.4, §4.5, §4.9), probado en el DOMINIO: la edad se deriva y nunca se persiste, el
 * tope es de servidor y viene de la instalación, quitar es desvincular si hay una firma detrás y
 * borrar si no, y un id ajeno no existe.
 *
 * El «hoy» de todo esto es el del PARQUE (`DisplayTime::today()`, doctrina `AFORO-09`): dos casos
 * cruzan la frontera UTC↔Madrid a propósito, porque con la fecha UTC un menor cumpliría 18 dos
 * horas tarde.
 */
class DependentRegistryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-08-27 12:00:00', 'Europe/Madrid'));
    }

    private function registry(): DependentRegistry
    {
        return app(DependentRegistry::class);
    }

    private function cap(string $raw): void
    {
        Setting::updateOrCreate(
            ['key' => DependentSettings::KEY_MAX_PER_ACCOUNT],
            ['value' => $raw, 'group' => DependentSettings::GROUP],
        );
    }

    /** Una firma del waiver EN NOMBRE del dependiente, por el único escritor que existe. */
    private function signFor(User $holder, Dependent $dependent): WaiverSignature
    {
        $version = app(LegalDocumentPublisher::class)->publish('waiver', [
            'es' => ['title' => 'Exención', 'body' => [['h' => 'Riesgo', 'p' => 'Saltar implica riesgos.']]],
        ])->first();

        return app(WaiverSigner::class)->sign($holder, $version, new WaiverSignatureRequest(
            channel: WaiverSignature::CHANNEL_WEB,
            ip: '10.0.0.7',
            userAgent: 'test',
            subjectType: WaiverSignature::SUBJECT_DEPENDENT,
            subjectId: (int) $dependent->getKey(),
        ));
    }

    // ─── Declarar ────────────────────────────────────────────────────────────

    public function test_adding_stores_name_and_birth_date_only_and_audits_without_pii(): void
    {
        $holder = User::factory()->create();

        $dependent = $this->registry()->add($holder, '  Lucas  ', '2017-03-12');

        $this->assertSame('Lucas', $dependent->name, 'el nombre se guarda recortado');
        $this->assertSame('2017-03-12', $dependent->born_on->toDateString());
        $this->assertNull($dependent->removed_at);
        $this->assertSame($holder->id, $dependent->user_id);
        $this->assertSame(9, $dependent->age());
        $this->assertTrue($dependent->isMinor());
        $this->assertSame('2035-03-12', $dependent->adultFrom()->toDateString());

        // `RGPD-02`: ni el nombre ni la fecha de nacimiento entran en la auditoría.
        $log = AuditLog::where('action', 'dependents.added')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($dependent->id, $log->payload['dependent_id']);
        $this->assertSame($holder->id, $log->target_id);
        $this->assertStringNotContainsString('Lucas', json_encode($log->payload));
        $this->assertStringNotContainsString('2017', json_encode($log->payload));
    }

    /** §4.2 — la edad NO existe como columna: cada columna nueva aquí es dato personal de un menor. */
    public function test_the_age_is_derived_and_never_persisted(): void
    {
        $columns = Schema::getColumnListing('dependents');
        sort($columns);

        $this->assertSame(
            ['born_on', 'created_at', 'id', 'name', 'removed_at', 'updated_at', 'user_id'],
            $columns,
            'la tabla tiene exactamente las columnas de la spec §4.2; una edad guardada sería una mentira con caducidad',
        );
    }

    /** §4.1 — la minoría de edad termina el día del 18.º cumpleaños, en el reloj del PARQUE. */
    public function test_minority_ends_on_the_18th_birthday_in_the_parks_clock(): void
    {
        $dependent = new Dependent(['born_on' => '2008-08-27']);

        $eve = Carbon::parse('2026-08-26 23:30:00', 'Europe/Madrid');
        $this->assertSame(17, $dependent->ageOn($eve));
        $this->assertTrue($dependent->isMinorOn($eve));

        // El cumpleaños a las 00:30 de Madrid —que en UTC todavía es el 26 a las 22:30—: 18.
        $birthday = Carbon::parse('2026-08-27 00:30:00', 'Europe/Madrid');
        $this->assertSame('2026-08-26', $birthday->copy()->utc()->toDateString(), 'el caso cruza la frontera UTC↔Madrid de verdad');
        $this->assertSame(18, $dependent->ageOn($birthday));
        $this->assertFalse($dependent->isMinorOn($birthday));
        $this->assertSame('2026-08-27', $dependent->adultFrom()->toDateString());

        // Y no hay edades negativas: antes de nacer, 0.
        $this->assertSame(0, $dependent->ageOn(Carbon::parse('2000-01-01', 'Europe/Madrid')));
    }

    /** El registro usa el «hoy» del parque, no el de UTC (mutación: `Carbon::today()` crudo deja pasar al adulto). */
    public function test_the_registry_uses_the_parks_today_for_the_minority_rule(): void
    {
        // 22:30 UTC del 26 = 00:30 del 27 en Madrid: en el parque ya cumplió 18.
        $this->travelTo(Carbon::parse('2026-08-26 22:30:00', 'UTC'));
        $holder = User::factory()->create();

        try {
            $this->registry()->add($holder, 'Ana', '2008-08-27');
            $this->fail('a las 00:30 de Madrid del cumpleaños ya es mayor de edad');
        } catch (DependentNotMinorException) {
        }

        $this->assertSame(0, Dependent::count());
    }

    public function test_an_adult_is_rejected_and_a_minor_by_one_day_is_accepted(): void
    {
        $holder = User::factory()->create();

        try {
            $this->registry()->add($holder, 'Ana', '2008-08-27');
            $this->fail('hoy cumple 18: ya no es menor, firma su propio waiver');
        } catch (DependentNotMinorException) {
        }
        $this->assertSame(0, Dependent::count());

        $accepted = $this->registry()->add($holder, 'Ana', '2008-08-28');
        $this->assertSame(17, $accepted->age());
        $this->assertTrue($accepted->isMinor());
    }

    public function test_a_future_or_malformed_birth_date_or_name_never_creates_a_row(): void
    {
        $holder = User::factory()->create();

        foreach (['2026-08-28', '2026-02-30', '12/03/2017', '2017-3-1', ''] as $bad) {
            try {
                $this->registry()->add($holder, 'Ana', $bad);
                $this->fail("«{$bad}» no puede crear una fila");
            } catch (InvalidArgumentException) {
            }
        }

        foreach (['', '   ', str_repeat('a', Dependent::NAME_MAX + 1)] as $badName) {
            try {
                $this->registry()->add($holder, $badName, '2017-03-12');
                $this->fail('un nombre vacío o demasiado largo no crea fila');
            } catch (InvalidArgumentException) {
            }
        }

        $this->assertSame(0, Dependent::count());
    }

    public function test_an_anonymised_holder_cannot_declare_dependents(): void
    {
        $holder = User::factory()->create();
        $holder->anonymize();

        try {
            $this->registry()->add($holder->fresh(), 'Ana', '2017-03-12');
            $this->fail('una cuenta anonimizada no declara personas a cargo');
        } catch (LogicException) {
        }

        $this->assertSame(0, Dependent::count());
    }

    // ─── El tope (§4.5) ──────────────────────────────────────────────────────

    /** §4.5 — el tope es de SERVIDOR y viene de la instalación. Mutación: sin la comprobación, entra la tercera. */
    public function test_the_cap_is_enforced_by_the_server_and_comes_from_the_settings(): void
    {
        $holder = User::factory()->create();
        $this->cap('2');

        $this->registry()->add($holder, 'Uno', '2015-01-01');
        $this->registry()->add($holder, 'Dos', '2016-01-01');

        try {
            $this->registry()->add($holder, 'Tres', '2017-01-01');
            $this->fail('la tercera supera el tope de 2');
        } catch (DependentsLimitReachedException $e) {
            $this->assertSame(2, $e->max);
        }
        $this->assertSame(2, Dependent::count());

        // El tope es POR CUENTA: otra cuenta empieza de cero.
        $this->registry()->add(User::factory()->create(), 'Ajeno', '2017-01-01');
        $this->assertSame(3, Dependent::count());
    }

    public function test_the_default_cap_is_twenty_and_an_invalid_setting_falls_back_to_it(): void
    {
        $this->assertSame(20, DependentSettings::maxPerAccount(), 'sin fila, el tope de la spec');

        foreach (['0', '-1', 'abc', '101', ''] as $bad) {
            $this->cap($bad);
            $this->assertSame(20, DependentSettings::maxPerAccount(), "«{$bad}» no puede abrir el tope");
        }

        $this->cap('100');
        $this->assertSame(100, DependentSettings::maxPerAccount());
    }

    public function test_removed_dependents_do_not_count_towards_the_cap(): void
    {
        $holder = User::factory()->create();
        $this->cap('1');

        // Borrada de verdad (sin firma) → libera el hueco.
        $first = $this->registry()->add($holder, 'Uno', '2015-01-01');
        $this->registry()->remove($holder, $first->id);
        $second = $this->registry()->add($holder, 'Dos', '2016-01-01');

        // Desvinculada (con firma detrás: la fila se queda) → también libera el hueco.
        $this->signFor($holder, $second);
        $this->assertSame(DependentRemoval::Unlinked, $this->registry()->remove($holder, $second->id));
        $this->assertDatabaseHas('dependents', ['id' => $second->id]);

        $this->registry()->add($holder, 'Tres', '2017-01-01');
        $this->assertSame(1, $this->registry()->activeFor($holder)->count());
    }

    // ─── Quitar (§4.4, §4.9) ─────────────────────────────────────────────────

    /** §4.4 — sin nada detrás, quitar BORRA la fila. */
    public function test_removing_without_references_deletes_the_row(): void
    {
        $holder = User::factory()->create();
        $dependent = $this->registry()->add($holder, 'Lucas', '2017-03-12');

        $mode = $this->registry()->remove($holder, $dependent->id);

        $this->assertSame(DependentRemoval::Deleted, $mode);
        $this->assertDatabaseMissing('dependents', ['id' => $dependent->id]);

        $log = AuditLog::where('action', 'dependents.removed')->latest('id')->first();
        $this->assertSame(['dependent_id' => $dependent->id, 'mode' => 'deleted'], $log->payload);
    }

    /** §4.4 — con un waiver firmado detrás, quitar DESVINCULA: la fila y la firma siguen ahí. */
    public function test_removing_with_a_waiver_signature_unlinks_and_keeps_the_row(): void
    {
        $holder = User::factory()->create();
        $dependent = $this->registry()->add($holder, 'Lucas', '2017-03-12');
        $signature = $this->signFor($holder, $dependent);

        $mode = $this->registry()->remove($holder, $dependent->id);

        $this->assertSame(DependentRemoval::Unlinked, $mode);
        $kept = Dependent::find($dependent->id);
        $this->assertNotNull($kept, 'la fila con firma detrás no se borra');
        $this->assertTrue($kept->isRemoved());
        $this->assertSame('Lucas', $kept->name, 'desvincular no anonimiza: el registro tiene que seguir identificando al sujeto');
        $this->assertDatabaseHas('waiver_signatures', ['id' => $signature->id, 'subject_id' => $dependent->id]);
        $this->assertTrue($signature->fresh()->verifyHash());

        // Fuera de la lista del titular.
        $this->assertSame([], $this->registry()->activeFor($holder)->pluck('id')->all());
        $this->assertSame('unlinked', AuditLog::where('action', 'dependents.removed')->latest('id')->value('payload')['mode']);
    }

    /** Una ENTRADA asignada al menor, escrita como la escribe `DependentAssigner` (tanda 4). */
    private function assignTicket(User $holder, Dependent $dependent): DependentAssignment
    {
        $zone = Zone::firstOrCreate(['slug' => 'jump'], ['name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true]);
        $entry = TicketType::firstOrCreate(['zone_id' => $zone->id, 'type' => TicketType::TYPE_ENTRY], [
            'name' => ['es' => 'Entrada'], 'duration_min' => 60, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
        $order = Order::create([
            'user_id' => $holder->id, 'code' => 'R-'.strtoupper(substr(md5((string) mt_rand()), 0, 6)),
            'status' => Order::STATUS_PAID, 'subtotal' => 500, 'tax' => 0, 'total' => 500, 'currency' => 'EUR', 'paid_at' => now(),
        ]);
        $item = $order->items()->create(['ticket_type_id' => $entry->id, 'quantity' => 1, 'unit_price' => 500, 'seats' => 1]);

        return DependentAssignment::create(['dependent_id' => $dependent->id, 'order_item_id' => $item->id]);
    }

    /** §4.4 (tanda 4) — con una ENTRADA asignada detrás, quitar también DESVINCULA: es la otra referencia. */
    public function test_removing_with_an_assigned_ticket_unlinks_and_keeps_the_row(): void
    {
        $holder = User::factory()->create();
        $dependent = $this->registry()->add($holder, 'Lucas', '2017-03-12');
        $assignment = $this->assignTicket($holder, $dependent);

        $mode = $this->registry()->remove($holder, $dependent->id);

        $this->assertSame(DependentRemoval::Unlinked, $mode);
        $this->assertTrue(Dependent::find($dependent->id)->isRemoved(), 'la fila con una entrada asignada no se borra');
        $this->assertDatabaseHas('dependent_assignments', ['id' => $assignment->id]);

        // Y desde ningún sitio: es la FK RESTRICT y la guarda de `deleting`, las dos.
        try {
            Dependent::find($dependent->id)->delete();
            $this->fail('un delete() sobre una fila con entradas asignadas tiene que lanzar');
        } catch (DependentHasReferencesException) {
        }
    }

    /** §4.4 — y desde NINGÚN sitio se puede borrar una fila con firma: es una guarda, no una convención. */
    public function test_a_referenced_dependent_cannot_be_deleted_from_anywhere(): void
    {
        $holder = User::factory()->create();
        $dependent = $this->registry()->add($holder, 'Lucas', '2017-03-12');
        $this->signFor($holder, $dependent);

        try {
            $dependent->delete();
            $this->fail('un delete() sobre una fila con firma tiene que lanzar');
        } catch (DependentHasReferencesException) {
        }

        $this->assertDatabaseHas('dependents', ['id' => $dependent->id, 'removed_at' => null]);
    }

    /** §4.9 — ajeno, inexistente y ya retirado responden LO MISMO (anti-IDOR). */
    public function test_a_foreign_missing_or_removed_dependent_cannot_be_removed(): void
    {
        $ana = User::factory()->create();
        $bea = User::factory()->create();
        $ofBea = $this->registry()->add($bea, 'De Bea', '2017-03-12');

        foreach ([$ofBea->id, 999_999] as $id) {
            try {
                $this->registry()->remove($ana, $id);
                $this->fail("el id {$id} no existe para Ana");
            } catch (DependentNotFoundException) {
            }
        }
        $this->assertDatabaseHas('dependents', ['id' => $ofBea->id, 'removed_at' => null]);

        $own = $this->registry()->add($ana, 'De Ana', '2017-03-12');
        $this->registry()->remove($ana, $own->id);
        try {
            $this->registry()->remove($ana, $own->id);
            $this->fail('una fila ya retirada tampoco existe');
        } catch (DependentNotFoundException) {
        }
    }

    /** §4.4 — quitar y volver a añadir son DOS filas: hacerse responsable otra vez es un acto nuevo. */
    public function test_re_adding_after_removal_is_a_new_row(): void
    {
        $holder = User::factory()->create();
        $first = $this->registry()->add($holder, 'Lucas', '2017-03-12');
        $this->signFor($holder, $first);
        $this->registry()->remove($holder, $first->id);

        $again = $this->registry()->add($holder, 'Lucas', '2017-03-12');

        $this->assertNotSame($first->id, $again->id);
        $this->assertSame(0, $again->waiverSignatures()->count(), 'la fila nueva necesita su propia firma');
        $this->assertSame([$again->id], $this->registry()->activeFor($holder)->pluck('id')->all());
    }
}
