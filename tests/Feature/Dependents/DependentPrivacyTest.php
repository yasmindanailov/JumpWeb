<?php

namespace Tests\Feature\Dependents;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Contracts\DependentRemoval;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\DependentAssignment;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Identity\Services\AccountPrivacy;
use App\Domain\Identity\Services\DependentRegistry;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Identity\Services\WaiverSigner;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fase 6 · menores a cargo — lo que el RGPD exige de una tabla con datos de MENORES
 * (`docs/specs/menores-a-cargo.md` §4.4, §5): en `User::anonymize()` cada persona a cargo sigue el
 * régimen de su waiver (`RGPD-01` ampliada), el export del art. 20 lleva las activas (`RGPD-04`),
 * la fila desvinculada que se queda sin firma se poda sola, y la limpieza de go-live la borra
 * ANTES que a su titular (`user_id` RESTRICT).
 */
class DependentPrivacyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-08-27 12:00:00', 'Europe/Madrid'));
    }

    private function add(User $holder, string $name, string $bornOn): Dependent
    {
        return app(DependentRegistry::class)->add($holder, $name, $bornOn);
    }

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

    /** Una ENTRADA del titular asignada al menor, tal como la escribe `DependentAssigner` (tanda 4). */
    private function assignTicket(User $holder, Dependent $dependent): OrderItem
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
        DependentAssignment::create(['dependent_id' => $dependent->id, 'order_item_id' => $item->id]);

        return $item;
    }

    /**
     * §5 + tanda 4 (D6) — `anonymize()` BORRA las asignaciones como vacía `guest_data`: PII de un menor
     * atada a una visita. Después cada menor sigue la regla de siempre con solo su firma como referencia:
     * el que tenía entradas y ninguna firma se BORRA; el que además firmó, se desvincula.
     */
    public function test_anonymize_deletes_the_assignments_and_then_treats_each_dependent_by_its_signature(): void
    {
        $holder = User::factory()->create();
        $onlyAssigned = $this->add($holder, 'Solo entradas', '2017-03-12');
        $assignedAndSigned = $this->add($holder, 'Entradas y firma', '2016-05-05');
        $item = $this->assignTicket($holder, $onlyAssigned);
        $this->assignTicket($holder, $assignedAndSigned);
        $this->signFor($holder, $assignedAndSigned);
        $this->assertSame(2, DependentAssignment::count());

        $this->assertTrue($holder->fresh()->anonymize());

        $this->assertSame(0, DependentAssignment::count(), 'las asignaciones se van con el art. 17, como las respuestas del pack');
        // El pedido se conserva (AEAT): solo cae la etiqueta.
        $this->assertDatabaseHas('order_items', ['id' => $item->id]);
        $this->assertDatabaseMissing('dependents', ['id' => $onlyAssigned->id]);
        $this->assertTrue(Dependent::find($assignedAndSigned->id)->isRemoved());
    }

    /** `RGPD-04` + tanda 4 (D6) — el export lleva, en cada línea, los NOMBRES de los menores para los que era. */
    public function test_the_export_carries_the_assigned_dependents_names_per_line_and_no_internal_id(): void
    {
        $holder = User::factory()->create();
        $lucas = $this->add($holder, 'Lior', '2017-03-12');
        $this->assignTicket($holder, $lucas);

        $export = app(AccountPrivacy::class)->exportFor($holder->fresh());

        $line = $export['orders'][0]['items'][0];
        $this->assertSame(['Lior'], $line['dependents']);
        $this->assertArrayNotHasKey('id', $line, 'el id del ítem es solo para cruzar: no se exporta');
        $this->assertSame(['product', 'date', 'time', 'quantity', 'unit_price_cents', 'seats', 'event_data', 'addons', 'dependents'], array_keys($line));
    }

    /** §4.4 + tanda 4 (D5) — una desvinculada CON entradas asignadas no se poda; cuando la cascada se las lleva, sí. */
    public function test_an_unlinked_dependent_with_assignments_is_kept_until_the_line_is_gone(): void
    {
        $holder = User::factory()->create();
        $registry = app(DependentRegistry::class);
        $lucas = $this->add($holder, 'Lior', '2017-03-12');
        $item = $this->assignTicket($holder, $lucas);
        $this->assertSame(DependentRemoval::Unlinked, $registry->remove($holder, $lucas->id));

        $this->artisan('model:prune', ['--model' => [Dependent::class]])->assertSuccessful();
        $this->assertDatabaseHas('dependents', ['id' => $lucas->id]);

        // La línea se borra físicamente (purga de go-live, verificador): la cascada se lleva la
        // asignación y la fila queda sin nada que la justifique.
        OrderItem::query()->whereKey($item->id)->delete();
        $this->artisan('model:prune', ['--model' => [Dependent::class]])->assertSuccessful();
        $this->assertDatabaseMissing('dependents', ['id' => $lucas->id]);
    }

    /** §5 (`RGPD-01` ampliada) — el dependiente sigue el régimen de su waiver. */
    public function test_anonymize_deletes_unreferenced_dependents_and_unlinks_the_signed_ones(): void
    {
        $holder = User::factory()->create();
        $plain = $this->add($holder, 'Sin firma', '2017-03-12');
        $signed = $this->add($holder, 'Con firma', '2016-05-05');
        $signature = $this->signFor($holder, $signed);

        $this->assertTrue($holder->fresh()->anonymize());

        // Sin firma: PII de un menor sin nada que la justifique → se borra de verdad.
        $this->assertDatabaseMissing('dependents', ['id' => $plain->id]);

        // Con firma: se CONSERVA vinculada y desvinculada de toda superficie, como la propia firma.
        $kept = Dependent::find($signed->id);
        $this->assertNotNull($kept, 'la fila con firma detrás sobrevive al art. 17 bajo el régimen restringido');
        $this->assertTrue($kept->isRemoved());
        $this->assertSame('Con firma', $kept->name, 'desvincular no anonimiza: el registro tiene que seguir identificando al sujeto');
        $this->assertSame($holder->id, $kept->user_id);
        $this->assertTrue($signature->fresh()->verifyHash());

        $anonymised = $holder->fresh();
        $this->assertTrue($anonymised->isAnonymized());
        $this->assertSame(0, $anonymised->dependents()->active()->count());
        $this->assertFalse($anonymised->anonymize(), 'sigue siendo idempotente');
        $this->assertDatabaseHas('dependents', ['id' => $signed->id]);
    }

    /** `RGPD-04` / spec §5 — el documento de portabilidad lleva las activas, y solo las activas. */
    public function test_the_export_document_carries_the_active_dependents_only(): void
    {
        $holder = User::factory()->create();
        $active = $this->add($holder, 'Lior', '2017-03-12');
        $unlinked = $this->add($holder, 'Vilma', '2019-11-02');
        $this->signFor($holder, $unlinked);
        $this->assertSame(DependentRemoval::Unlinked, app(DependentRegistry::class)->remove($holder, $unlinked->id));

        $export = app(AccountPrivacy::class)->exportFor($holder->fresh());

        $this->assertSame([[
            'name' => 'Lior',
            'born_on' => '2017-03-12',
            'added_at' => $active->created_at->toIso8601String(),
        ]], $export['dependents']);
        $this->assertStringNotContainsString('Vilma', json_encode($export), 'la retirada con firma vive bajo el régimen restringido, fuera del art. 20');
    }

    /** §4.4 + §5 — la fila DESVINCULADA que se queda sin firma se poda; la que aún la tiene y la activa, no. */
    public function test_orphan_unlinked_dependents_are_pruned_and_the_rest_are_kept(): void
    {
        $holder = User::factory()->create();
        $registry = app(DependentRegistry::class);

        $orphan = $this->add($holder, 'Huérfana', '2017-03-12');
        $this->signFor($holder, $orphan);
        $registry->remove($holder, $orphan->id);

        $kept = $this->add($holder, 'Con firma', '2016-05-05');
        $this->signFor($holder, $kept);
        $registry->remove($holder, $kept->id);

        $active = $this->add($holder, 'Activa', '2018-01-01');

        // La poda por plazo se lleva la firma de la primera (por debajo del modelo, como `Prunable`).
        DB::table('waiver_signatures')
            ->where('subject_type', WaiverSignature::SUBJECT_DEPENDENT)
            ->where('subject_id', $orphan->id)
            ->delete();

        $this->artisan('model:prune', ['--model' => [Dependent::class]])->assertSuccessful();

        $this->assertDatabaseMissing('dependents', ['id' => $orphan->id]);
        $this->assertDatabaseHas('dependents', ['id' => $kept->id]);
        $this->assertDatabaseHas('dependents', ['id' => $active->id, 'removed_at' => null]);
    }

    public function test_the_prune_is_wired_in_the_scheduler_after_the_signatures(): void
    {
        $events = collect(app(Schedule::class)->events());

        $prune = $events->first(fn ($event): bool => str_contains((string) $event->command, 'model:prune')
            && str_contains((string) $event->command, 'Dependent'));

        $this->assertNotNull($prune, 'la poda de personas a cargo tiene que estar programada (routes/console.php)');
        $command = (string) $prune->command;
        $this->assertLessThan(
            strpos($command, 'Dependent'),
            strpos($command, 'WaiverSignature'),
            'las firmas se podan ANTES que las filas que referencian: en la misma pasada, una firma vencida deja huérfana a su fila',
        );
    }

    /** La limpieza de go-live borra las personas a cargo ANTES que los usuarios (`user_id` RESTRICT). */
    public function test_the_go_live_purge_removes_the_dependents_of_purged_holders(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $admin = User::factory()->create(['email' => 'admin-keep@x.test']);
        $admin->roles()->sync([Role::where('name', 'admin')->value('id')]);
        $customer = User::factory()->create(['email' => 'cliente@x.test']);
        $dependent = $this->add($customer, 'Lior', '2017-03-12');
        $this->signFor($customer, $dependent);
        // Y con una entrada asignada (tanda 4): la cascada desde `order_items` la borra ANTES que a
        // los menores, así que la limpieza no tiene que conocerla.
        $this->assignTicket($customer, $dependent);
        $this->assertSame(1, DependentAssignment::count());

        $this->artisan('app:purge-customers', ['--keep' => ['admin-keep@x.test'], '--force' => true])->assertSuccessful();

        $this->assertDatabaseMissing('users', ['email' => 'cliente@x.test']);
        $this->assertDatabaseMissing('dependents', ['id' => $dependent->id]);
        $this->assertDatabaseMissing('waiver_signatures', ['subject_id' => $dependent->id]);
        $this->assertSame(0, DependentAssignment::count(), 'la asignación cae con la línea del pedido purgado');
        $this->assertDatabaseHas('users', ['email' => 'admin-keep@x.test']);
    }
}
