<?php

namespace Tests\Feature\Sales;

use App\Domain\Booking\Models\OpeningHour;
use App\Domain\Booking\Models\SpecialDate;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Services\OperatingSchedule;
use App\Domain\Booking\Services\ProductAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Transversal §9.2 — Ventana de disponibilidad por producto. (1) Ventana base: la franja
 * debe empezar dentro de [apertura, cierre) del día, SIEMPRE (#208). (2) Offsets del
 * producto: restricción adicional. Sin horario configurado no se ancla y no restringe; día
 * cerrado no admite nada.
 */
class ProductAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private ProductAvailability $window;

    private Carbon $date;   // miércoles con horario 10:00–21:00

    protected function setUp(): void
    {
        parent::setUp();

        $this->window = new ProductAvailability(new OperatingSchedule);
        $this->date = Carbon::parse('2026-06-10'); // miércoles
        OpeningHour::create(['weekday' => $this->date->dayOfWeek, 'open_time' => '10:00:00', 'close_time' => '21:00:00']);
    }

    private function product(int $afterOpen = 0, int $beforeClose = 0): TicketType
    {
        return TicketType::create([
            'name' => ['es' => 'Entrada'],
            'is_sellable' => true,
            'is_active' => true,
            'seats_per_unit' => 1,
            'position' => 1,
            'available_after_open_min' => $afterOpen,
            'available_before_close_min' => $beforeClose,
        ]);
    }

    public function test_default_window_allows_slots_inside_opening_hours(): void
    {
        $product = $this->product();

        $this->assertTrue($this->window->allowsStart($product, $this->date, '10:00:00')); // apertura
        $this->assertTrue($this->window->allowsStart($product, $this->date, '20:00:00')); // dentro
    }

    public function test_base_window_rejects_slots_before_open_even_without_offsets(): void
    {
        // #208: el bug — con offsets a 0, una franja antes de la apertura se ofrecía igual.
        $product = $this->product(); // sin offsets

        $this->assertFalse($this->window->allowsStart($product, $this->date, '09:00:00')); // antes de 10:00
    }

    public function test_base_window_rejects_slots_at_or_after_close_even_without_offsets(): void
    {
        $product = $this->product(); // sin offsets

        $this->assertTrue($this->window->allowsStart($product, $this->date, '20:00:00'));  // última franja válida
        $this->assertFalse($this->window->allowsStart($product, $this->date, '21:00:00')); // == cierre
        $this->assertFalse($this->window->allowsStart($product, $this->date, '22:00:00')); // tras cierre
    }

    public function test_after_open_offset_excludes_early_slots(): void
    {
        $product = $this->product(afterOpen: 120); // disponible desde 12:00

        $this->assertFalse($this->window->allowsStart($product, $this->date, '11:00:00'));
        $this->assertTrue($this->window->allowsStart($product, $this->date, '12:00:00'));
    }

    public function test_before_close_offset_excludes_late_slots(): void
    {
        $product = $this->product(beforeClose: 120); // disponible hasta 19:00

        $this->assertTrue($this->window->allowsStart($product, $this->date, '19:00:00'));
        $this->assertFalse($this->window->allowsStart($product, $this->date, '20:00:00'));
    }

    public function test_no_restriction_when_day_has_no_configured_hours(): void
    {
        $product = $this->product(afterOpen: 120, beforeClose: 120);
        $thursday = $this->date->copy()->addDay(); // sin opening_hours → sin anclaje

        $this->assertTrue($this->window->allowsStart($product, $thursday, '00:01:00'));
    }

    public function test_closed_day_disallows_everything(): void
    {
        $product = $this->product();
        SpecialDate::create(['date' => $this->date->toDateString(), 'is_closed' => true]);

        $this->assertFalse($this->window->allowsStart($product, $this->date, '12:00:00'));
    }
}
