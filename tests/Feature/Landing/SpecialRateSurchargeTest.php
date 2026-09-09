<?php

namespace Tests\Feature\Landing;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\TicketType;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **LA TARIFA ESPECIAL EN LA LANDING: su PRECIO ENTERO, nunca su recargo.**
 *
 * Cubre las dos mitades, que son de naturaleza distinta:
 *  · el DOMINIO — las reglas de robustez de {@see TicketType::specialRateSurcharges()}: data-driven,
 *    N tarifas, base `normal`, delta por producto y tarifa, signo no garantizado. **Ahí sigue
 *    calculándose el recargo y no se ha tocado**: lo necesitan el panel y los informes.
 *  · la PRESENTACIÓN — desde `#479` la web publica `priceCents` y **nunca** `surchargeCents`
 *    (`[DECIDIDO owner, 2026-09-09]`), y los días se escriben una vez por sección.
 *
 * ⚠️ *Que el dominio siga sabiendo el recargo no es un resto: es la separación correcta.* Lo que
 * cambió es a quién se le enseña.
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

    /**
     * ❗❗❗ **LA REGLA CAMBIÓ EN `#479` Y ESTOS TRES CASOS LO FIJAN: nunca un recargo.**
     *
     * `[DECIDIDO owner, 2026-09-09]` sobre una regla dura del sistema del canvas: *«un recargo no se
     * publica como recargo. Y menos si no es plano: en cumpleaños es +2 € en Kids y +4 € en Jump,
     * así que el cliente tendría que recordar cuál le toca. El precio, entero»*.
     *
     * ⚠️⚠️ **La aserción que de verdad protege esto es la NEGATIVA.** Que salga «14 €» es fácil de
     * conseguir por accidente —la tarjeta escribe varios importes—; lo que no puede volver es el
     * «+», y por eso cada caso lo prohíbe explícitamente. *Un caso que solo comprueba lo que SÍ se
     * ve deja entrar de nuevo lo que se acaba de retirar.*
     */
    public function test_the_home_section_writes_the_whole_special_price_and_never_a_surcharge(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('por persona', $html);                       // la unidad
        $this->assertStringContainsString(__('landing.rates.special_suffix'), $html);
        $this->assertStringNotContainsString('entre semana', $html);                   // nota vieja

        /*
         * ⚠️⚠️ **La negativa se acota a la LÍNEA de tarifa especial, y no al documento.** Un
         * `assertDontSee('+2,00')` sobre la página entera sale rojo con el producto SANO: los
         * complementos escriben su precio con «+» —«+ Calcetines · 2,00 €»— y ése es un signo
         * legítimo, porque un complemento **sí** se suma a lo que compras. Lo que no puede llevar
         * signo es la tarifa especial, que es un precio alternativo y no un añadido.
         * *Aseverar sobre el documento entero mide el ruido de al lado, no la regla.*
         */
        preg_match_all('#<p class="rate-card__special">(.*?)</p>#s', $html, $m);
        $this->assertNotEmpty($m[1], 'ninguna tarjeta pinta tarifa especial: este caso miraría el vacío.');

        foreach ($m[1] as $linea) {
            $this->assertStringNotContainsString('+', $linea, 'la tarifa especial ha vuelto a publicarse como recargo.');
        }
    }

    /**
     * **Los días se escriben UNA vez por sección, no dentro de cada tarjeta.**
     *
     * ⚠️ Es la otra mitad de la regla: al sacar los días del chip, «en tarifa especial» se queda sin
     * significado si nadie lo define. La nota lo define — y **una sola vez**, que es lo que este
     * caso cuenta. Con la etiqueta repetida por tarjeta volvería el ruido que la regla quita.
     */
    public function test_the_special_rate_days_are_written_once_per_section(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();

        preg_match('#<section id="pricing".*?</section>#s', $html, $m);
        $this->assertNotEmpty($m, 'la sección de tarifas perdió su `id`: este caso miraría el vacío.');

        // El rótulo de la tarifa aparece EXACTAMENTE una vez por panel de zona, dentro de su nota.
        $this->assertStringContainsString('Findes y festivos', $m[0]);
        $this->assertSame(
            substr_count($m[0], 'rates__note'),
            substr_count($m[0], 'Findes y festivos'),
            'el nombre de la tarifa especial se escribe fuera de su nota: han vuelto los días por tarjeta.',
        );
    }

    public function test_pack_card_writes_the_whole_special_price_too(): void
    {
        // Packs (cumpleaños) reutilizan el MISMO componente, así que heredan la regla entera.
        $html = (string) $this->get('/cumpleanos')->assertOk()->getContent();

        // ⚠️ «18» y no «18,00»: los importes de ESCAPARATE se escriben sin ceros a la derecha
        // (`Money::showcase()`), al revés que los de transacción. Es la diferencia que `#479`
        // subió a esa clase para que no hubiera dos formas sueltas de escribir un precio.
        preg_match_all('#<span class="price__special-line">(.*?)</span>\s*</span>#s', $html, $m);
        $this->assertNotEmpty($m[1], 'el pack no pinta tarifa especial: este caso miraría el vacío.');

        $this->assertStringContainsString('18', $m[1][0]);
        $this->assertStringContainsString(__('landing.rates.special_suffix'), $m[1][0]);
        $this->assertStringNotContainsString('+', $m[1][0], 'el pack ha vuelto a publicar su recargo.');
    }

    public function test_cheaper_special_renders_its_own_amount_too(): void
    {
        // Una especial MÁS BARATA que la normal ya no era un caso aparte desde `#479` —todas se
        // escriben enteras—, pero se conserva porque es el borde donde el signo tentaba: si alguien
        // reintrodujera el recargo, aquí saldría un «−» o un «+» sobre un número negativo.
        $this->reprice($this->anEntry(), ['normal' => 1777, 'special' => 1499]);

        $res = $this->get('/precios');

        $res->assertOk();
        $res->assertSee('14,99');
        $res->assertDontSee('+14,99');
        $res->assertDontSee('-2,78');
    }
}
