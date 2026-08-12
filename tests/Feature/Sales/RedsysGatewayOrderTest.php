<?php

namespace Tests\Feature\Sales;

use App\Domain\Platform\Models\Setting;
use App\Models\Order;
use App\Support\Redsys;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fase 5.5a — Contador atómico de `Ds_Merchant_Order` (Redsys §5).
 *
 * Redsys exige un número de pedido único POR COMERCIO+TERMINAL para siempre (reusarlo
 * dispara el error `0913` "pedido repetido"). El contador vive en
 * `settings.redsys_next_gateway_order` y se incrementa dentro de una transacción con
 * `lockForUpdate`. Estos tests blindan la robustez del generador.
 */
class RedsysGatewayOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_next_gateway_order_uses_seeded_counter_when_present(): void
    {
        Setting::create(['key' => 'redsys_next_gateway_order', 'value' => '200000', 'group' => 'payment']);

        $first = (new Redsys)->nextGatewayOrder();

        $this->assertSame('0000200000', $first);
        $this->assertSame('200001', Setting::value('redsys_next_gateway_order'));
    }

    public function test_next_gateway_order_falls_back_to_100000_when_setting_missing(): void
    {
        // Sin seed previo: el contador debe arrancar en 100000 para garantizar ≥4 dígitos
        // numéricos (los 4 primeros caracteres son obligatorios numéricos, manual §5).
        $first = (new Redsys)->nextGatewayOrder();

        $this->assertSame('0000100000', $first);
        $this->assertSame('100001', Setting::value('redsys_next_gateway_order'));
    }

    public function test_next_gateway_order_is_monotonic_and_unique(): void
    {
        $redsys = new Redsys;
        $generated = [];

        for ($i = 0; $i < 50; $i++) {
            $generated[] = $redsys->nextGatewayOrder();
        }

        // No reusos.
        $this->assertSame($generated, array_unique($generated));

        // Monotónicamente creciente (en orden de generación).
        $sorted = $generated;
        sort($sorted, SORT_STRING);
        $this->assertSame($generated, $sorted, 'gateway_order values must be monotonic.');
    }

    public function test_next_gateway_order_never_exceeds_redsys_12_char_limit(): void
    {
        // Aunque el contador parta de un valor alto cercano al tope, el padding no debe
        // pasar de 12 caracteres (manual §5 + error `SIS0075`).
        Setting::create(['key' => 'redsys_next_gateway_order', 'value' => '9999999999', 'group' => 'payment']);

        $value = (new Redsys)->nextGatewayOrder();

        $this->assertLessThanOrEqual(12, strlen($value));
        $this->assertSame('9999999999', $value);
        $this->assertTrue(ctype_digit(substr($value, 0, 4)), 'First 4 chars must be numeric (Redsys §5).');
    }

    public function test_next_gateway_order_is_transactional(): void
    {
        // Si la transacción interna falla, el contador NO debe avanzar (atomicidad).
        // Forzamos un fallo dentro de la transacción consumidora envolviendo en una
        // transacción externa que rollback-eará después.
        $redsys = new Redsys;
        $before = (int) (Setting::value('redsys_next_gateway_order') ?? 100000);

        try {
            DB::transaction(function () use ($redsys) {
                $redsys->nextGatewayOrder();
                throw new \RuntimeException('forced rollback');
            });
        } catch (\RuntimeException) {
            // esperado
        }

        $after = (int) (Setting::value('redsys_next_gateway_order') ?? 100000);

        // El contador no debe haber avanzado porque la transacción externa hizo rollback.
        $this->assertSame($before, $after, 'Counter must not advance if the outer transaction rolls back.');
    }

    // ─── Suelo anti-colisión (#169, edge case empírico 2026-06-02) ───────

    public function test_next_gateway_order_skips_past_higher_existing_payment(): void
    {
        // El contador NO es la única fuente de unicidad: el siguiente valor nunca
        // puede ser ≤ un `gateway_order` ya emitido, aunque el contador vaya por
        // detrás (p. ej. tras un reset). Salta al máximo emitido + 1.
        Setting::create(['key' => 'redsys_next_gateway_order', 'value' => '100000', 'group' => 'payment']);
        $this->insertPayment('0000500000');

        $next = (new Redsys)->nextGatewayOrder();

        $this->assertSame('0000500001', $next);
        $this->assertFalse(DB::table('payments')->where('gateway_order', $next)->exists());
    }

    public function test_next_gateway_order_self_heals_when_counter_reset_below_issued(): void
    {
        // Reproducción EXACTA del bug #169: el contador se reinició a 100000
        // (un `db:seed` reescribía el setting) mientras un payment con
        // 0000100000 seguía vivo. SIN el suelo, nextGatewayOrder devolvería
        // 0000100000 → duplicate key en `payments.gateway_order` → todo intento
        // de pago falla en bucle (solo lo frena el rate limit) creando pedidos
        // caducados. Con el suelo, salta por encima y se autorrepara.
        Setting::create(['key' => 'redsys_next_gateway_order', 'value' => '100000', 'group' => 'payment']);
        $this->insertPayment('0000100000');

        $next = (new Redsys)->nextGatewayOrder();

        $this->assertNotSame('0000100000', $next, 'No debe devolver el valor ya emitido (colisión en bucle).');
        $this->assertSame('0000100001', $next);
        $this->assertFalse(DB::table('payments')->where('gateway_order', $next)->exists());
    }

    public function test_next_gateway_order_respects_counter_when_ahead_of_payments(): void
    {
        // Operación normal: el contador va por delante de los pagos emitidos →
        // se respeta tal cual (el suelo no interfiere), preservando la secuencia.
        Setting::create(['key' => 'redsys_next_gateway_order', 'value' => '300000', 'group' => 'payment']);
        $this->insertPayment('0000100000');

        $this->assertSame('0000300000', (new Redsys)->nextGatewayOrder());
    }

    public function test_reseeding_does_not_reset_live_gateway_order_counter(): void
    {
        // Causa raíz del #169: el seeder reescribía el contador en cada `db:seed`.
        // Ahora se siembra con firstOrCreate → un re-seed NO toca el valor vivo.
        $this->seed(LandingContentSeeder::class);
        Setting::where('key', 'redsys_next_gateway_order')->update(['value' => '777777']);

        $this->seed(LandingContentSeeder::class); // re-seed (operativa real)

        $this->assertSame('777777', Setting::value('redsys_next_gateway_order'),
            'El re-seed NO debe resetear el contador operativo de gateway_order (#169).');
    }

    public function test_seeder_starts_the_counter_from_a_time_derived_value(): void
    {
        // #169 (seguimiento 2026-06-09, verificado contra la doc de Redsys): el `Ds_Merchant_Order`
        // debe ser único DE POR VIDA y Redsys recuerda los ya emitidos (sandbox incluido). Sembrar
        // el contador desde el TIMESTAMP hace que cada `migrate:fresh`/clon nuevo arranque POR
        // ENCIMA de sesiones previas → no choca con la memoria del sandbox (SIS0051 / Ds_Response
        // 0913). En línea con la recomendación de Redsys de un nº derivado de fecha/hora. No toca
        // el algoritmo de generación (contador monotónico + suelo anti-colisión), solo el arranque.
        $this->seed(LandingContentSeeder::class);

        $seeded = (int) Setting::value('redsys_next_gateway_order');

        $this->assertGreaterThanOrEqual(now()->subDay()->timestamp, $seeded);
        $this->assertLessThanOrEqual(now()->addDay()->timestamp, $seeded);
        // Sigue cumpliendo el formato Redsys: primeras 4 numéricas y ≤12 chars.
        $this->assertTrue(ctype_digit((string) $seeded));
        $this->assertLessThanOrEqual(12, strlen((string) $seeded));
    }

    private function insertPayment(string $gatewayOrder): void
    {
        DB::table('payments')->insert([
            'payable_type' => (new Order)->getMorphClass(),
            'payable_id' => 1,
            'provider' => 'redsys',
            'amount' => 1000,
            'currency' => 'EUR',
            'status' => 'pending',
            'gateway_order' => $gatewayOrder,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
