<?php

namespace Tests\Feature\Dependents;

use App\Domain\Booking\Contracts\CheckoutLine;
use App\Domain\Booking\Contracts\CheckoutLines;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\DependentAssignment;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Identity\Services\DependentAssigner;
use App\Domain\Identity\Services\DependentRegistry;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Identity\Services\WaiverSigner;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Tests\Support\DeclaresDependents;
use Tests\TestCase;

/**
 * Fase 6 · menores a cargo, tanda 4 — la ASIGNACIÓN de entradas a menores, probada en el DOMINIO
 * (`docs/specs/menores-a-cargo.md` §4.7–§4.10, §9.9.3 D3–D5, §9.9.5; `DECISIONES #202`).
 *
 * Las reglas, en las DOS fases: el menor es suyo y activo (ajeno = inexistente = retirado, §4.9), es
 * menor EN LA FECHA DE LA VISITA, tiene la exención firmada y VIGENTE en modo interno (`#202`·2),
 * nunca más menores que unidades, y solo en entradas. Y lo que la escritura promete: idempotente,
 * atada a SU ítem por lo que Booking dice, y sin poder tirar el pedido si falla.
 */
class DependentAssignerTest extends TestCase
{
    use DeclaresDependents;
    use RefreshDatabase;

    private const VISIT = '2026-09-05';

    private Zone $zone;

    private TicketType $entry;

    private TicketType $pack;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-08-27 12:00:00', 'Europe/Madrid'));

        // El catálogo describe cada producto con su tarifa: sin la «normal», `RateResolver` lanza.
        $rateId = (int) RateType::create([
            'key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0,
        ])->id;
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true]);
        $this->entry = TicketType::create([
            'name' => ['es' => 'Entrada 1h'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
        $this->entry->prices()->create(['rate_type_id' => $rateId, 'amount_cents' => 990]);
        $this->pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $this->zone->id,
            'duration_min' => 120, 'min_qty' => 2, 'max_qty' => 20, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => 2,
        ]);
        $this->pack->prices()->create(['rate_type_id' => $rateId, 'amount_cents' => 1500]);
    }

    private function assigner(): DependentAssigner
    {
        return app(DependentAssigner::class);
    }

    private function add(User $holder, string $name = 'Lior', string $bornOn = '2017-03-12'): Dependent
    {
        return $this->declareLegacyDependent($holder, $name, $bornOn);
    }

    private function mode(string $mode): void
    {
        Setting::updateOrCreate(['key' => 'waiver.mode'], ['value' => $mode, 'group' => 'waiver']);
        Setting::flushMemo();
    }

    private ?LegalDocumentVersion $version = null;

    /** Publica una versión NUEVA del waiver: la vigente pasa a ser esta, y lo firmado antes queda «anterior». */
    private function publish(): LegalDocumentVersion
    {
        return $this->version = app(LegalDocumentPublisher::class)->publish('waiver', [
            'es' => ['title' => 'Exención', 'body' => [['h' => 'Riesgo', 'p' => 'Saltar implica riesgos.']]],
        ])->first();
    }

    /** Firma en nombre del menor sobre la versión VIGENTE (publicándola si aún no hay ninguna). */
    private function signFor(User $holder, Dependent $dependent): WaiverSignature
    {
        $version = $this->version ?? $this->publish();

        return app(WaiverSigner::class)->sign($holder, $version, new WaiverSignatureRequest(
            channel: WaiverSignature::CHANNEL_WEB,
            ip: '10.0.0.7',
            userAgent: 'test',
            subjectType: WaiverSignature::SUBJECT_DEPENDENT,
            subjectId: (int) $dependent->getKey(),
        ));
    }

    /** Un pedido con sus líneas PRINCIPALES creadas en el orden dado (el mismo que la cesta). */
    private function order(User $holder, array $lines): Order
    {
        $order = Order::create([
            'user_id' => $holder->id, 'code' => 'R-'.strtoupper(substr(md5((string) mt_rand()), 0, 6)),
            'status' => Order::STATUS_PENDING, 'subtotal' => 1000, 'tax' => 0, 'total' => 1000,
            'currency' => 'EUR', 'expires_at' => now()->addHour(),
        ]);
        // La MISMA franja para todos los pedidos del test (la clave es única por zona, día y hora).
        $slot = Slot::firstOrCreate(
            ['zone_id' => $this->zone->id, 'date' => self::VISIT, 'start_time' => '10:00:00'],
            ['end_time' => '11:00:00', 'capacity' => 20, 'online_capacity' => 20],
        );
        foreach ($lines as [$type, $quantity]) {
            $order->items()->create([
                'ticket_type_id' => $type->id, 'slot_id' => $slot->id, 'quantity' => $quantity,
                'unit_price' => 500, 'seats' => $quantity,
            ]);
        }

        return $order;
    }

    /** @return list<array{index:int, product_id:int, date:string, quantity:int, dependent_ids:list<int>}> */
    private function request(array $perLine): array
    {
        $lines = [];
        foreach ($perLine as $index => [$type, $quantity, $ids]) {
            $lines[] = ['index' => $index, 'product_id' => $type->id, 'date' => self::VISIT, 'quantity' => $quantity, 'dependent_ids' => $ids];
        }

        return $lines;
    }

    // ─── FASE 1 · check(): las reglas, antes del dinero ──────────────────────

    public function test_check_accepts_the_holders_minors_with_a_current_waiver(): void
    {
        $this->mode('interno');
        $holder = User::factory()->create();
        $lucas = $this->add($holder);
        $vera = $this->add($holder, 'Vilma', '2019-11-02');
        $this->signFor($holder, $lucas);
        $this->signFor($holder, $vera);

        $this->assertSame([], $this->assigner()->check($holder, $this->request([[$this->entry, 2, [$lucas->id, $vera->id]]])));
        $this->assertSame([], $this->assigner()->check($holder, $this->request([[$this->entry, 2, []]])), 'sin ids no hay nada que comprobar');
    }

    /** §4.9 — ajeno, inexistente y retirado responden LO MISMO, y en el campo de ESE id. */
    public function test_check_rejects_foreign_missing_and_removed_dependents_alike(): void
    {
        $this->mode('externo');
        $ana = User::factory()->create();
        $bea = User::factory()->create();
        $ofBea = $this->add($bea, 'De Bea');
        $removed = $this->add($ana, 'Retirado');
        app(DependentRegistry::class)->remove($ana, $removed->id);
        $own = $this->add($ana, 'Propio');

        $errors = $this->assigner()->check($ana, $this->request([[$this->entry, 4, [$own->id, $ofBea->id, 999_999, $removed->id]]]));

        $message = __('api.dependents.not_yours');
        $this->assertSame([
            'items.0.dependent_ids.1' => [$message],
            'items.0.dependent_ids.2' => [$message],
            'items.0.dependent_ids.3' => [$message],
        ], $errors, 'el propio pasa; los otros tres caen con el MISMO mensaje, cada uno en su posición');
    }

    /** D13 — la minoría se decide EN LA FECHA DE LA VISITA, no hoy. */
    public function test_check_rejects_a_dependent_who_is_an_adult_on_the_visit_date(): void
    {
        $this->mode('externo');
        $holder = User::factory()->create();
        // Cumple 18 el 1 de septiembre: hoy (27/08) es menor; el día de la visita (05/09), no.
        $adultOnVisit = $this->add($holder, 'Casi', '2008-09-01');
        $stillMinor = $this->add($holder, 'Aún', '2008-09-10');

        $errors = $this->assigner()->check($holder, $this->request([[$this->entry, 2, [$adultOnVisit->id, $stillMinor->id]]]));

        $this->assertSame(['items.0.dependent_ids.0' => [__('api.dependents.not_minor_on_date')]], $errors);
    }

    /** `[DECIDIDO owner]` `#202`·2 — en modo interno, sin exención firmada y VIGENTE no se asigna. */
    public function test_check_requires_a_current_waiver_in_internal_mode_only(): void
    {
        $holder = User::factory()->create();
        $lucas = $this->add($holder);
        $request = $this->request([[$this->entry, 1, [$lucas->id]]]);

        // Fuera del modo interno no hay firma que comprobar: pasa.
        $this->mode('externo');
        $this->assertSame([], $this->assigner()->check($holder, $request));
        $this->mode('desactivado');
        $this->assertSame([], $this->assigner()->check($holder, $request));

        // En interno: sin firma, no.
        $this->mode('interno');
        $this->publish();
        $this->assertSame(['items.0.dependent_ids.0' => [__('api.dependents.waiver_unsigned')]], $this->assigner()->check($holder, $request));

        // Con firma vigente, sí.
        $this->signFor($holder, $lucas);
        $this->assertSame([], $this->assigner()->check($holder, $request));

        // Y una firma de una versión ANTERIOR tampoco vale: la vigente es la que cubre.
        $this->publish();
        $this->assertSame(['items.0.dependent_ids.0' => [__('api.dependents.waiver_unsigned')]], $this->assigner()->check($holder, $request));
    }

    public function test_check_rejects_more_dependents_than_units_on_the_line(): void
    {
        $this->mode('externo');
        $holder = User::factory()->create();
        $lucas = $this->add($holder);
        $vera = $this->add($holder, 'Vilma', '2019-11-02');

        $errors = $this->assigner()->check($holder, $this->request([[$this->entry, 1, [$lucas->id, $vera->id]]]));

        $this->assertSame(['items.0.dependent_ids' => [__('api.dependents.too_many')]], $errors);
    }

    /** §4.7 — solo entradas: un pack ya pide a sus invitados. */
    public function test_check_rejects_dependents_on_a_pack_line(): void
    {
        $this->mode('externo');
        $holder = User::factory()->create();
        $lucas = $this->add($holder);

        $errors = $this->assigner()->check($holder, $this->request([
            [$this->pack, 4, [$lucas->id]],
            [$this->entry, 1, [$lucas->id]],
        ]));

        $this->assertSame(['items.0.dependent_ids' => [__('api.dependents.entries_only')]], $errors, 'la entrada de la línea 1 pasa; el pack de la 0 no');
    }

    /** Los cinco avisos existen en los TRES idiomas y dicen cosas distintas (`ClientMoneyLabelsAreTranslatedTest`, misma doctrina). */
    public function test_the_notices_exist_in_the_three_locales_and_differ(): void
    {
        foreach (['not_yours', 'not_minor_on_date', 'waiver_unsigned', 'too_many', 'entries_only'] as $key) {
            $texts = [];
            foreach (['es', 'en', 'fr'] as $locale) {
                $this->assertTrue(Lang::has("api.dependents.{$key}", $locale, false), "falta api.dependents.{$key} en {$locale}");
                $texts[] = Lang::get("api.dependents.{$key}", [], $locale);
            }
            $this->assertCount(3, array_unique($texts), "api.dependents.{$key} repite texto entre idiomas");
        }
    }

    // ─── FASE 2 · assign(): la escritura, después del `allow` ───────────────

    public function test_assign_writes_each_dependent_on_its_line_and_audits_without_pii(): void
    {
        $this->mode('externo');
        $holder = User::factory()->create();
        $lucas = $this->add($holder);
        $vera = $this->add($holder, 'Vilma', '2019-11-02');
        $order = $this->order($holder, [[$this->entry, 2], [$this->entry, 1]]);
        [$first, $second] = $order->items()->orderBy('id')->get();

        $outcome = $this->assigner()->assign($holder, $order->id, $this->request([
            [$this->entry, 2, [$lucas->id, $vera->id]],
            [$this->entry, 1, [$lucas->id]],
        ]));

        $this->assertSame(3, $outcome->assigned);
        $this->assertSame(0, $outcome->skipped);
        $this->assertNull($outcome->abortedBecause);
        $this->assertEqualsCanonicalizing(
            [$lucas->id, $vera->id],
            DependentAssignment::where('order_item_id', $first->id)->pluck('dependent_id')->all(),
        );
        $this->assertSame([$lucas->id], DependentAssignment::where('order_item_id', $second->id)->pluck('dependent_id')->all());

        $logs = AuditLog::where('action', 'dependents.assigned')->get();
        $this->assertCount(3, $logs);
        $this->assertSame(['dependent_id' => $lucas->id, 'order_item_id' => $first->id, 'order_id' => $order->id], $logs->first()->payload);
        $this->assertStringNotContainsString('Lior', json_encode($logs), 'RGPD-02: la auditoría lleva ids, nunca el nombre');
    }

    /** §4.10 — idempotente: una segunda pasada no escribe ni audita dos veces. */
    public function test_assign_is_idempotent(): void
    {
        $this->mode('externo');
        $holder = User::factory()->create();
        $lucas = $this->add($holder);
        $order = $this->order($holder, [[$this->entry, 2]]);
        $request = $this->request([[$this->entry, 2, [$lucas->id]]]);

        $this->assertSame(1, $this->assigner()->assign($holder, $order->id, $request)->assigned);
        $again = $this->assigner()->assign($holder, $order->id, $request);

        $this->assertSame(0, $again->assigned);
        $this->assertNull($again->abortedBecause, 'la segunda pasada se PROCESA sin escribir: no es un fallo capturado');
        $this->assertSame(1, DependentAssignment::count());
        $this->assertSame(1, AuditLog::where('action', 'dependents.assigned')->count());
    }

    /** Las reglas se RE-validan bajo el lock: lo que cambió entre las dos fases se salta, el resto entra. */
    public function test_assign_revalidates_and_skips_what_can_no_longer_be_assigned(): void
    {
        $this->mode('externo');
        $holder = User::factory()->create();
        $lucas = $this->add($holder);
        $vera = $this->add($holder, 'Vilma', '2019-11-02');
        $order = $this->order($holder, [[$this->entry, 2]]);
        Log::spy();

        // Entre `check()` y `assign()` el titular retiró a Vilma desde otra pestaña.
        app(DependentRegistry::class)->remove($holder, $vera->id);

        $outcome = $this->assigner()->assign($holder, $order->id, $this->request([[$this->entry, 2, [$lucas->id, $vera->id]]]));

        $this->assertSame(1, $outcome->assigned);
        $this->assertSame(1, $outcome->skipped);
        $this->assertSame([$lucas->id], DependentAssignment::pluck('dependent_id')->all(), 'la línea se queda sin ESA asignación, no se rompe (§4.8·2)');
        Log::shouldHaveReceived('warning')->withArgs(fn (string $msg, array $ctx): bool => $msg === 'dependents.assign_skipped' && $ctx['reason'] === DependentAssigner::REASON_NOT_YOURS)->once();
    }

    /** Solo entradas, también al escribir: una línea de pack con ids se salta entera. */
    public function test_assign_skips_pack_lines(): void
    {
        $this->mode('externo');
        $holder = User::factory()->create();
        $lucas = $this->add($holder);
        $order = $this->order($holder, [[$this->pack, 4], [$this->entry, 1]]);

        $outcome = $this->assigner()->assign($holder, $order->id, $this->request([[$this->pack, 4, [$lucas->id]], [$this->entry, 1, [$lucas->id]]]));

        $this->assertSame(1, $outcome->assigned);
        $this->assertSame(1, $outcome->skipped);
        $this->assertSame([$order->items()->orderBy('id')->get()[1]->id], DependentAssignment::pluck('order_item_id')->all());
    }

    /**
     * D2 — la guarda de correlación: si el pedido no tiene EXACTAMENTE las líneas que la cesta tenía,
     * nadie sabe qué ítem es qué línea y no se escribe NADA. Mejor sin asignar que asignado a otra línea.
     */
    public function test_assign_writes_nothing_when_the_order_lines_do_not_match_the_cart(): void
    {
        $this->mode('externo');
        $holder = User::factory()->create();
        $lucas = $this->add($holder);
        $order = $this->order($holder, [[$this->entry, 2]]);
        Log::spy();

        $outcome = $this->assigner()->assign($holder, $order->id, $this->request([
            [$this->entry, 2, [$lucas->id]],
            [$this->entry, 1, []],
        ]));

        $this->assertSame('line_mismatch', $outcome->abortedBecause);
        $this->assertSame(0, DependentAssignment::count());
        Log::shouldHaveReceived('warning')->withArgs(fn (string $msg, array $ctx): bool => $msg === 'dependents.assign_skipped' && $ctx['reason'] === 'line_mismatch')->once();
    }

    /** Y un pedido AJENO tampoco tiene líneas para este titular (anti-IDOR por el contrato). */
    public function test_assign_writes_nothing_on_a_foreign_order(): void
    {
        $this->mode('externo');
        $ana = User::factory()->create();
        $bea = User::factory()->create();
        $lucas = $this->add($ana);
        $order = $this->order($bea, [[$this->entry, 2]]);

        $outcome = $this->assigner()->assign($ana, $order->id, $this->request([[$this->entry, 2, [$lucas->id]]]));

        $this->assertSame('line_mismatch', $outcome->abortedBecause);
        $this->assertSame(0, DependentAssignment::count());
    }

    /** §4.10 — si la escritura falla, NO lanza: el pedido ya existe y esto es una etiqueta. */
    public function test_assign_never_throws_and_leaves_the_order_alone_when_it_fails(): void
    {
        $this->mode('externo');
        $holder = User::factory()->create();
        $lucas = $this->add($holder);
        $order = $this->order($holder, [[$this->entry, 2]]);
        $this->app->instance(CheckoutLines::class, new class implements CheckoutLines
        {
            public function forOrder(int $orderId, int $userId): array
            {
                throw new \RuntimeException('Booking no responde');
            }
        });
        Log::spy();

        $outcome = app(DependentAssigner::class)->assign($holder, $order->id, $this->request([[$this->entry, 2, [$lucas->id]]]));

        $this->assertSame('failed', $outcome->abortedBecause);
        $this->assertSame(0, DependentAssignment::count());
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => Order::STATUS_PENDING]);
        Log::shouldHaveReceived('warning')->withArgs(fn (string $msg): bool => $msg === 'dependents.assign_failed')->once();
    }

    // ─── La lectura (D4/D7) ──────────────────────────────────────────────────

    /** Lo asignado se lee por ítem, y la coherencia con la cantidad ACTUAL se deriva al leer. */
    public function test_reading_returns_the_dependents_per_item_capped_to_the_current_quantity(): void
    {
        $this->mode('externo');
        $holder = User::factory()->create();
        $lucas = $this->add($holder);
        $vera = $this->add($holder, 'Vilma', '2019-11-02');
        $order = $this->order($holder, [[$this->entry, 2]]);
        $item = $order->items()->firstOrFail();
        $this->assigner()->assign($holder, $order->id, $this->request([[$this->entry, 2, [$lucas->id, $vera->id]]]));

        $read = $this->assigner()->forOrderItems([$item->id]);
        $this->assertSame([$lucas->id, $vera->id], array_map(fn (Dependent $d): int => $d->id, $read[$item->id]));

        // El panel bajó la línea a UNA unidad (`OrderItemEditor::edit()` no borra filas): se enseña la primera.
        $item->forceFill(['quantity' => 1])->save();
        $capped = $this->assigner()->forOrderItems([$item->id], [$item->id => 1]);
        $this->assertSame([$lucas->id], array_map(fn (Dependent $d): int => $d->id, $capped[$item->id]));

        // Y un menor RETIRADO sigue saliendo: la reserva fue para él.
        app(DependentRegistry::class)->remove($holder, $vera->id);
        $this->assertSame([$lucas->id, $vera->id], array_map(fn (Dependent $d): int => $d->id, $this->assigner()->forOrderItems([$item->id])[$item->id]));
        $this->assertSame([], $this->assigner()->forOrderItems([]), 'sin ítems no consulta nada');
    }

    /** El reader REAL de Booking atribuye el índice por orden de creación, que es el de la cesta (D2). */
    public function test_the_booking_reader_orders_the_principal_lines_by_creation(): void
    {
        $holder = User::factory()->create();
        $order = $this->order($holder, [[$this->entry, 2], [$this->pack, 4]]);
        $items = $order->items()->orderBy('id')->get();
        // Un complemento colgado del primero NO es una línea.
        $order->items()->create(['ticket_type_id' => $this->entry->id, 'parent_item_id' => $items[0]->id, 'quantity' => 1, 'unit_price' => 100, 'seats' => 0]);

        $lines = app(CheckoutLines::class)->forOrder($order->id, $holder->id);

        $this->assertCount(2, $lines);
        $this->assertSame([0, 1], array_map(fn (CheckoutLine $l): int => $l->index, $lines));
        $this->assertSame([$items[0]->id, $items[1]->id], array_map(fn (CheckoutLine $l): int => $l->orderItemId, $lines));
        $this->assertSame([true, false], array_map(fn (CheckoutLine $l): bool => $l->isEntry, $lines));
        $this->assertSame([2, 4], array_map(fn (CheckoutLine $l): int => $l->quantity, $lines));
        $this->assertSame(self::VISIT, $lines[0]->date);
        $this->assertSame([], app(CheckoutLines::class)->forOrder($order->id, User::factory()->create()->id), 'un pedido ajeno no tiene líneas');
    }

    /** La cascada: al borrar la LÍNEA (purga de go-live, verificadores) la asignación se va con ella. */
    public function test_an_assignment_falls_with_its_order_item(): void
    {
        $this->mode('externo');
        $holder = User::factory()->create();
        $lucas = $this->add($holder);
        $order = $this->order($holder, [[$this->entry, 1]]);
        $this->assigner()->assign($holder, $order->id, $this->request([[$this->entry, 1, [$lucas->id]]]));
        $this->assertSame(1, DependentAssignment::count());

        OrderItem::query()->whereKey($order->items()->firstOrFail()->id)->delete();

        $this->assertSame(0, DependentAssignment::count());
        $this->assertDatabaseHas('dependents', ['id' => $lucas->id]);
    }

    // ─── El MOSTRADOR (tanda 5, spec §9.10.2 D14·3) ──────────────────────────

    /** D14: la lista que ve el operador — los ACTIVOS del titular, con el motivo de cada uno que no se puede marcar ese día. */
    public function test_candidates_lists_the_active_minors_with_their_reason_on_that_date(): void
    {
        $this->mode('interno');
        $holder = User::factory()->create();
        $lucas = $this->add($holder);                             // firmado → asignable
        $vera = $this->add($holder, 'Vilma', '2019-11-02');        // sin firma
        $noa = $this->add($holder, 'Noa', '2008-09-01');          // 17 hoy, 18 el día de la visita
        $max = $this->add($holder, 'Max', '2016-01-01');          // se retira: no sale
        $this->signFor($holder, $lucas);
        $this->signFor($holder, $max);
        app(DependentRegistry::class)->remove($holder, $max->id);
        $this->add(User::factory()->create(), 'Ajeno', '2017-01-01');

        $candidates = $this->assigner()->candidates($holder, self::VISIT);

        $this->assertSame([$lucas->id, $vera->id, $noa->id], array_map(fn (array $c): int => $c['dependent']->id, $candidates));
        $this->assertSame(
            [null, DependentAssigner::REASON_WAIVER_UNSIGNED, DependentAssigner::REASON_NOT_MINOR_ON_DATE],
            array_column($candidates, 'reason'),
        );
        $this->assertSame([], $this->assigner()->candidates(User::factory()->create(), self::VISIT), 'sin menores no hay lista');

        // Fuera del modo interno no hay firma que mirar: Vilma pasa a ser asignable.
        $this->mode('externo');
        $this->assertNull($this->assigner()->candidates($holder, self::VISIT)[1]['reason']);
    }

    /** D14·3: el sync pone y quita para dejar EXACTAMENTE el conjunto pedido, y audita cada fila con el OPERADOR. */
    public function test_sync_adds_and_removes_to_match_the_requested_set_and_audits_with_the_operator(): void
    {
        $this->mode('externo');
        $holder = User::factory()->create();
        $lucas = $this->add($holder);
        $vera = $this->add($holder, 'Vilma', '2019-11-02');
        $order = $this->order($holder, [[$this->entry, 2]]);
        $item = $order->items()->firstOrFail();
        $this->assigner()->assign($holder, $order->id, $this->request([[$this->entry, 2, [$lucas->id]]]));
        $operator = User::factory()->create();
        $this->actingAs($operator);

        $outcome = $this->assigner()->sync($holder, $order->id, $item->id, [$vera->id]);

        $this->assertTrue($outcome->ok());
        $this->assertSame([1, 1, 0], [$outcome->added, $outcome->removed, $outcome->kept]);
        $this->assertSame([$vera->id], DependentAssignment::where('order_item_id', $item->id)->pluck('dependent_id')->all());

        $unassigned = AuditLog::where('action', 'dependents.unassigned')->sole();
        $this->assertSame($operator->id, (int) $unassigned->user_id, 'D12: en el mostrador el `by` es el operador');
        $this->assertSame(['dependent_id' => $lucas->id, 'order_item_id' => $item->id, 'order_id' => $order->id], $unassigned->payload);
        $assigned = AuditLog::where('action', 'dependents.assigned')->orderByDesc('id')->firstOrFail();
        $this->assertSame($operator->id, (int) $assigned->user_id);
        $this->assertSame($vera->id, $assigned->payload['dependent_id']);
        $this->assertStringNotContainsString('Vilma', json_encode(AuditLog::all()), 'RGPD-02');
    }

    public function test_sync_is_idempotent_and_writes_nothing_for_the_same_set(): void
    {
        $this->mode('externo');
        $holder = User::factory()->create();
        $lucas = $this->add($holder);
        $order = $this->order($holder, [[$this->entry, 2]]);
        $item = $order->items()->firstOrFail();
        $this->assertTrue($this->assigner()->sync($holder, $order->id, $item->id, [$lucas->id])->changed());

        $again = $this->assigner()->sync($holder, $order->id, $item->id, [$lucas->id, $lucas->id]);

        $this->assertTrue($again->ok());
        $this->assertFalse($again->changed());
        $this->assertSame(1, $again->kept);
        $this->assertSame(1, DependentAssignment::count());
        $this->assertSame(1, AuditLog::whereIn('action', ['dependents.assigned', 'dependents.unassigned'])->count());

        $emptied = $this->assigner()->sync($holder, $order->id, $item->id, []);
        $this->assertSame(1, $emptied->removed);
        $this->assertSame(0, DependentAssignment::count());
    }

    /** FAIL-CLOSED: un rechazo en lo que se AÑADE deja la línea como estaba, y dice en qué POSICIÓN. */
    public function test_sync_writes_nothing_when_anything_added_is_rejected_and_reports_the_position(): void
    {
        $this->mode('interno');
        $holder = User::factory()->create();
        $lucas = $this->add($holder);
        $vera = $this->add($holder, 'Vilma', '2019-11-02');
        $this->signFor($holder, $lucas);
        $order = $this->order($holder, [[$this->entry, 3]]);
        $item = $order->items()->firstOrFail();
        $foreign = $this->add(User::factory()->create(), 'Ajeno', '2017-01-01');

        $outcome = $this->assigner()->sync($holder, $order->id, $item->id, [$lucas->id, $vera->id, $foreign->id]);

        $this->assertFalse($outcome->ok());
        $this->assertSame(['1' => DependentAssigner::REASON_WAIVER_UNSIGNED, '2' => DependentAssigner::REASON_NOT_YOURS], $outcome->rejections);
        $this->assertSame(0, DependentAssignment::count(), 'con un rechazo no se escribe NADA, tampoco Lior');
        $this->assertSame(0, AuditLog::where('action', 'dependents.assigned')->count());

        $tooMany = $this->assigner()->sync($holder, $order->id, $item->id, [$lucas->id, $lucas->id, $vera->id, $foreign->id, 999]);
        $this->assertSame(['' => DependentAssigner::REASON_TOO_MANY], $tooMany->rejections, 'el tope se mira sobre ids DISTINTOS y antes que las reglas');
    }

    /** D14·3: lo asignado a un menor ya RETIRADO se conserva —el operador no lo ve— y cuenta para el tope. */
    public function test_sync_conserves_assignments_to_unlinked_dependents_and_counts_them(): void
    {
        $this->mode('externo');
        $holder = User::factory()->create();
        $lucas = $this->add($holder);
        $vera = $this->add($holder, 'Vilma', '2019-11-02');
        $max = $this->add($holder, 'Max', '2016-01-01');
        $order = $this->order($holder, [[$this->entry, 2]]);
        $item = $order->items()->firstOrFail();
        $this->assigner()->assign($holder, $order->id, $this->request([[$this->entry, 2, [$lucas->id, $vera->id]]]));
        // Vilma se retira de la cuenta: con una entrada asignada detrás, se DESVINCULA (§4.4).
        app(DependentRegistry::class)->remove($holder, $vera->id);
        $this->assertNotNull($vera->fresh()->removed_at);

        // Mantener a Lior: Vilma sigue ahí sin que el operador la haya tocado.
        $same = $this->assigner()->sync($holder, $order->id, $item->id, [$lucas->id]);
        $this->assertTrue($same->ok());
        $this->assertFalse($same->changed());
        $this->assertEqualsCanonicalizing([$lucas->id, $vera->id], DependentAssignment::where('order_item_id', $item->id)->pluck('dependent_id')->all());

        // Y ocupa una unidad: Lior + Max serían tres en una línea de dos.
        $full = $this->assigner()->sync($holder, $order->id, $item->id, [$lucas->id, $max->id]);
        $this->assertSame(['' => DependentAssigner::REASON_TOO_MANY], $full->rejections);

        // Cambiar a Lior por Max sí cabe: Vilma se conserva, Lior sale, Max entra.
        $swap = $this->assigner()->sync($holder, $order->id, $item->id, [$max->id]);
        $this->assertSame([1, 1, 0], [$swap->added, $swap->removed, $swap->kept]);
        $this->assertEqualsCanonicalizing([$vera->id, $max->id], DependentAssignment::where('order_item_id', $item->id)->pluck('dependent_id')->all());
    }

    /** Lo que se MANTIENE no se re-valida (como `assign()` con `insertOrIgnore`); lo que se AÑADE, sí. */
    public function test_sync_does_not_revalidate_what_is_kept_but_validates_what_is_added(): void
    {
        $this->mode('interno');
        $holder = User::factory()->create();
        $lucas = $this->add($holder);
        $vera = $this->add($holder, 'Vilma', '2019-11-02');
        $this->signFor($holder, $lucas);
        $order = $this->order($holder, [[$this->entry, 2]]);
        $item = $order->items()->firstOrFail();
        $this->assertTrue($this->assigner()->sync($holder, $order->id, $item->id, [$lucas->id])->ok());

        // Se publica un texto nuevo: la firma de Lior queda «anterior» y ya no sería asignable de nuevo…
        $this->publish();
        $this->assertSame(DependentAssigner::REASON_WAIVER_UNSIGNED, $this->assigner()->candidates($holder, self::VISIT)[0]['reason']);

        // …pero mantenerlo en la línea no se bloquea.
        $kept = $this->assigner()->sync($holder, $order->id, $item->id, [$lucas->id]);
        $this->assertTrue($kept->ok());
        $this->assertSame(1, $kept->kept);

        // Añadir a Vilma (sin firma) sí se rechaza, y no se escribe nada.
        $added = $this->assigner()->sync($holder, $order->id, $item->id, [$lucas->id, $vera->id]);
        $this->assertSame(['1' => DependentAssigner::REASON_WAIVER_UNSIGNED], $added->rejections);
        $this->assertSame([$lucas->id], DependentAssignment::pluck('dependent_id')->all());
    }

    /** Un ítem que no es del pedido del titular NO EXISTE (`no_line`); un pack se rechaza como línea. */
    public function test_sync_aborts_on_a_foreign_item_and_rejects_pack_lines(): void
    {
        $this->mode('externo');
        $holder = User::factory()->create();
        $lucas = $this->add($holder);
        $mine = $this->order($holder, [[$this->entry, 1], [$this->pack, 4]]);
        [$entryItem, $packItem] = $mine->items()->orderBy('id')->get();
        $other = $this->order(User::factory()->create(), [[$this->entry, 1]]);
        $foreignItem = $other->items()->firstOrFail();

        $this->assertSame('no_line', $this->assigner()->sync($holder, $other->id, $foreignItem->id, [$lucas->id])->abortedBecause);
        $this->assertSame('no_line', $this->assigner()->sync($holder, $mine->id, $foreignItem->id, [$lucas->id])->abortedBecause, 'el ítem tiene que ser de ESE pedido');
        $this->assertSame('no_line', $this->assigner()->sync($holder, $mine->id, 999999, [])->abortedBecause);
        $this->assertSame(['' => DependentAssigner::REASON_ENTRIES_ONLY], $this->assigner()->sync($holder, $mine->id, $packItem->id, [$lucas->id])->rejections);
        $this->assertSame(0, DependentAssignment::count());
        $this->assertTrue($this->assigner()->sync($holder, $mine->id, $entryItem->id, [$lucas->id])->ok());
    }

    /** Nunca lanza: si el contrato de Booking cae, el operador recibe un resultado, no una excepción. */
    public function test_sync_never_throws(): void
    {
        $this->mode('externo');
        $holder = User::factory()->create();
        $lucas = $this->add($holder);
        $order = $this->order($holder, [[$this->entry, 1]]);
        $item = $order->items()->firstOrFail();
        Log::spy();
        $this->app->instance(CheckoutLines::class, new class implements CheckoutLines
        {
            public function forOrder(int $orderId, int $userId): array
            {
                throw new \RuntimeException('booking caído');
            }
        });

        $outcome = app(DependentAssigner::class)->sync($holder, $order->id, $item->id, [$lucas->id]);

        $this->assertSame('failed', $outcome->abortedBecause);
        $this->assertFalse($outcome->ok());
        $this->assertSame(0, DependentAssignment::count());
        Log::shouldHaveReceived('warning')->withArgs(fn (string $msg): bool => $msg === 'dependents.sync_failed')->once();
    }
}
