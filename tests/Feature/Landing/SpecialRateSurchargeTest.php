<?php

namespace Tests\Feature\Landing;

use App\Models\RateType;
use App\Models\TicketType;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Suplemento de TARIFA ESPECIAL en la card de la landing (presentación «+X€»).
 *
 * Cubre las reglas de robustez de {@see TicketType::specialRateSurcharges()} (data-driven,
 * N tarifas, base `normal`, delta por producto/tarifa, signo no garantizado) y su render en
 * la card (recargo vs importe absoluto + etiqueta i18n de la tarifa + nota «entre semana»).
 */
class SpecialRateSurchargeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);
    }

    /** Primera entrada sembrada (jump · 1 hora). */
    private function anEntry(): TicketType
    {
        return TicketType::ofType(TicketType::TYPE_ENTRY)->orderBy('position')->firstOrFail();
    }

    /** Reescribe los precios de una entrada por clave de tarifa (null = sin precio) y la recarga. */
    private function reprice(TicketType $ticket, array $centsByKey): TicketType
    {
        $rates = RateType::pluck('id', 'key');
        $ticket->prices()->delete();

        foreach ($centsByKey as $key => $cents) {
            if ($cents !== null) {
                $ticket->prices()->create([
                    'rate_type_id' => $rates[$key],
                    'amount_cents' => $cents,
                    'currency' => 'EUR',
                ]);
            }
        }

        return $ticket->fresh(['prices.rateType']);
    }

    public function test_positive_surcharge_over_normal(): void
    {
        $rows = $this->reprice($this->anEntry(), ['normal' => 1000, 'special' => 1300])
            ->specialRateSurcharges();

        $this->assertCount(1, $rows);
        $this->assertSame(300, $rows[0]['surchargeCents']);
        $this->assertSame(1300, $rows[0]['priceCents']);
        $this->assertSame(RateType::KEY_SPECIAL, $rows[0]['rate']->key);
    }

    public function test_special_cheaper_than_normal_gives_negative_surcharge(): void
    {
        // El panel no valida el signo: una «especial» más barata es configurable.
        $rows = $this->reprice($this->anEntry(), ['normal' => 1000, 'special' => 800])
            ->specialRateSurcharges();

        $this->assertCount(1, $rows);
        $this->assertSame(-200, $rows[0]['surchargeCents']);
        $this->assertSame(800, $rows[0]['priceCents']);
    }

    public function test_no_normal_price_means_no_rows(): void
    {
        // Sin base `normal` no hay «+X sobre el precio de diario» con sentido.
        $rows = $this->reprice($this->anEntry(), ['special' => 1300])->specialRateSurcharges();

        $this->assertSame([], $rows);
    }

    public function test_special_equal_to_normal_is_excluded(): void
    {
        // Misma cifra que la base → no aporta info → no se anuncia.
        $rows = $this->reprice($this->anEntry(), ['normal' => 1000, 'special' => 1000])
            ->specialRateSurcharges();

        $this->assertSame([], $rows);
    }

    public function test_inactive_special_rate_is_excluded(): void
    {
        RateType::where('key', RateType::KEY_SPECIAL)->update(['is_active' => false]);

        $rows = $this->reprice($this->anEntry(), ['normal' => 1000, 'special' => 1300])
            ->specialRateSurcharges();

        $this->assertSame([], $rows);
    }

    public function test_multiple_special_rates_are_listed_ordered_by_priority(): void
    {
        // La clienta añade una 2.ª tarifa especial desde el panel (data-driven, N tarifas).
        RateType::create([
            'key' => 'verano',
            'label' => ['es' => 'Verano', 'en' => 'Summer', 'fr' => 'Été'],
            'is_special' => true,
            'weekdays' => null,
            'priority' => 20,               // mayor prioridad que `special` (10)
            'is_active' => true,
        ]);

        $rows = $this->reprice($this->anEntry(), [
            'normal' => 1000,
            'special' => 1300,   // priority 10
            'verano' => 1500,    // priority 20
        ])->specialRateSurcharges();

        $this->assertCount(2, $rows);
        $this->assertSame(RateType::KEY_SPECIAL, $rows[0]['rate']->key);   // prioridad ascendente
        $this->assertSame('verano', $rows[1]['rate']->key);
        $this->assertSame(300, $rows[0]['surchargeCents']);
        $this->assertSame(500, $rows[1]['surchargeCents']);
    }

    public function test_home_card_renders_period_surcharge_chip_and_label(): void
    {
        $res = $this->get('/');

        $res->assertOk();
        $res->assertSee('por persona');       // unidad (mismo estilo/texto que «por niño» del pack)
        $res->assertSee('+2,00');             // recargo del fixture (normal+200)
        $res->assertSee('Findes y festivos'); // etiqueta i18n de la tarifa (label sembrado)
        $res->assertDontSee('entre semana');  // nota retirada
    }

    public function test_pack_card_renders_the_same_surcharge_chip(): void
    {
        // Packs (cumpleaños) reutilizan el MISMO chip (componente compartido, «solo el chip»).
        $res = $this->get('/cumpleanos');

        $res->assertOk();
        $res->assertSee('+3,00');             // recargo del pack en el fixture (1800 − 1500)
        $res->assertSee('Findes y festivos'); // misma etiqueta i18n que en entradas
    }

    public function test_cheaper_special_renders_absolute_amount_not_a_surcharge(): void
    {
        // Especial MÁS BARATA que la normal → la card muestra el importe ABSOLUTO, sin «+».
        $this->reprice($this->anEntry(), ['normal' => 1777, 'special' => 1499]);

        $res = $this->get('/precios');

        $res->assertOk();
        $res->assertSee('14,99');       // importe absoluto de la especial
        $res->assertDontSee('+14,99');  // NUNCA como recargo
    }
}
