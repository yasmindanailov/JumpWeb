<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RateType;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Database\ClassMorphViolationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fase 2 — morphMap FORZADO (prerequisito de la modularización, DECISIONES #10/#11 vía
 * DEUDA §Alta): las columnas polimórficas persisten ALIAS estables, nunca FQCN, para que
 * renombrar/mover modelos en la modularización no rompa datos.
 */
class MorphMapTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_eloquent_model_has_a_stable_morph_alias(): void
    {
        // Todo modelo NUEVO debe registrarse en el mapa de AppServiceProvider: este test
        // falla (vía ClassMorphViolationException implícita en getMorphClass) si se olvida.
        // Cubre la raíz heredada Y los módulos de Fase 2 (app/Domain/<Ctx>/Models), para que
        // el guard no se vacíe conforme los modelos emigran (hallazgo de la revisión del spec).
        $files = array_merge(glob(app_path('Models/*.php')), glob(app_path('Domain/*/Models/*.php')));
        $this->assertNotEmpty($files, 'el guard no puede pasar en vacío');

        foreach ($files as $file) {
            $class = str_replace(['/', '.php'], ['\\', ''], 'App'.mb_substr($file, mb_strlen(app_path())));
            if (! is_subclass_of($class, Model::class)) {
                continue;
            }

            $alias = (new $class)->getMorphClass();

            $this->assertNotSame($class, $alias, "{$class} sin alias en el morphMap");
            $this->assertMatchesRegularExpression('/^[a-z0-9_]+$/', $alias, "alias no-snake para {$class}");
            $this->assertSame($class, Relation::getMorphedModel($alias), "alias «{$alias}» no resuelve de vuelta");
        }
    }

    public function test_morph_relations_persist_aliases_and_round_trip(): void
    {
        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $type = TicketType::create([
            'name' => ['es' => 'Entrada'], 'type' => TicketType::TYPE_ENTRY,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
        $price = $type->prices()->create([
            'rate_type_id' => RateType::where('key', 'normal')->value('id'),
            'amount_cents' => 1000,
        ]);

        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'R-MORPH1',
            'status' => Order::STATUS_PENDING, 'subtotal' => 1000, 'total' => 1000, 'currency' => 'EUR',
        ]);
        $payment = $order->payments()->create([
            'amount' => 1000, 'currency' => 'EUR', 'provider' => 'redsys',
            'status' => Payment::STATUS_PENDING, 'gateway_order' => '9990001234',
        ]);

        // En BD viven los ALIAS…
        $this->assertSame('ticket_type', $price->getRawOriginal('priceable_type'));
        $this->assertSame('order', $payment->getRawOriginal('payable_type'));

        // …y las relaciones resuelven ida y vuelta.
        $this->assertTrue($price->fresh()->priceable->is($type));
        $this->assertTrue($payment->fresh()->payable->is($order));
        $this->assertTrue($order->payments()->first()->is($payment));
    }

    public function test_legacy_fqcn_rows_still_resolve_for_migration_safety(): void
    {
        // La migración convert_morph_types_to_aliases convierte los datos; este test documenta
        // el cinturón: una fila legacy con FQCN (pre-migración) sigue RESOLVIENDO su relación
        // (fallback de lectura de Laravel), aunque las queries por alias no la vean.
        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'R-MORPH2',
            'status' => Order::STATUS_PENDING, 'subtotal' => 1000, 'total' => 1000, 'currency' => 'EUR',
        ]);
        DB::table('payments')->insert([
            'payable_type' => 'App\\Models\\Order', 'payable_id' => $order->id,
            'amount' => 1000, 'currency' => 'EUR', 'provider' => 'redsys',
            'status' => Payment::STATUS_PENDING, 'gateway_order' => '9990005678',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $legacy = Payment::where('gateway_order', '9990005678')->firstOrFail();
        $this->assertTrue($legacy->payable->is($order));
    }

    public function test_audit_logs_persist_target_aliases(): void
    {
        $user = User::factory()->create();
        $log = AuditLog::create([
            'action' => 'test.morph', 'target_type' => $user->getMorphClass(),
            'target_id' => $user->id, 'payload' => [],
            'payload_hash' => hash('sha256', '[]'),
        ]);

        $this->assertSame('user', $log->getRawOriginal('target_type'));
        $this->assertTrue($log->fresh()->target->is($user));
    }

    public function test_morphing_an_unmapped_class_throws(): void
    {
        // `enforceMorphMap`: ningún FQCN nuevo puede colarse en BD por un modelo sin alias.
        $rogue = new class extends Model
        {
            protected $table = 'orders';
        };

        $this->expectException(ClassMorphViolationException::class);
        $rogue->getMorphClass();
    }
}
