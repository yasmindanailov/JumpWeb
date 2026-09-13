<?php

namespace Tests\Feature\Reservation;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * La PIEL del formulario post-reserva y de su molde (`docs/specs/celebracion-e-invitacion.md` §4.2, `#570`).
 *
 * ▶ Vigila las ocho grietas que el canvas midió (`doc/formulario.md`, F-01…F-08) donde se pueden volver
 * a abrir SIN QUE NADA FALLE: el color de zona haciendo de estado, las sombras de modal, los radios y
 * las tallas fuera de la escala, el asterisco rojo y la política metida en su frase.
 *
 * ⚠️ Mira el BLOQUE de la hoja en `site.css` y **sin comentarios**: la prosa de este fichero nombra a
 * propósito lo que se retiró (`--zone-1`, `--shadow-modal`…), y una aserción por subcadena sobre el
 * texto con comentarios acusaría al código por su propia explicación (`#553`).
 */
class GuestFormSkinTest extends TestCase
{
    use RefreshDatabase;

    /** Las seis tallas de la hoja más las dos cifras de rótulo del cubo y los titulares. */
    private const SIZES = [
        'var(--fs-title)', 'var(--fs-lede)', 'var(--fs-body)', 'var(--fs-body-s)',
        'var(--fs-button)', 'var(--fs-label)', 'var(--fs-20)', 'var(--fs-18)',
    ];

    private const RADII = ['var(--r-md)', 'var(--r)', 'var(--r-pill)'];

    public function test_the_sheet_paints_nothing_with_the_zone_colour(): void
    {
        $this->assertStringNotContainsString('--zone-', $this->sheetCss(), 'el color de zona volvió a hacer de estado (F-01)');
    }

    public function test_only_the_sheet_furniture_casts_a_shadow(): void
    {
        $css = $this->sheetCss();
        preg_match_all('/box-shadow:\s*([^;}]+)/', $css, $m);

        // Tres muebles con su ROL y nada más (T2, `#571`): el aviso de guardado y la barra pegada son
        // mobiliario —la familia `--shadow-nav`, porque `--shadow-float` vale una sombra DURA con el paquete
        // (`#265`)— y el diálogo del pegado es un modal. Una ficha abierta NO se eleva (F-04).
        $allowed = ['var(--shadow-nav)', 'var(--shadow-nav-dock)', 'var(--shadow-modal)'];
        $this->assertSame([], array_values(array_diff(array_map('trim', $m[1]), $allowed)), 'una sombra fuera de los tres muebles de la hoja (F-04)');
        $this->assertStringNotContainsString('radial-gradient', $css, 'la trama de puntos no es de la marca (F-06)');
    }

    public function test_radii_and_type_stay_on_the_sheet_scale(): void
    {
        $css = $this->sheetCss();

        preg_match_all('/border-radius:\s*([^;}]+)/', $css, $radii);
        $this->assertSame([], array_values(array_diff(array_map('trim', $radii[1]), self::RADII)), 'un radio fuera de la escala de la hoja (F-05)');

        preg_match_all('/font-size:\s*([^;}]+)/', $css, $sizes);
        $this->assertSame([], array_values(array_diff(array_map('trim', $sizes[1]), self::SIZES)), 'una talla fuera de la escala de la hoja (F-02)');
    }

    public function test_the_page_marks_what_is_optional_and_offers_the_policy_as_a_control(): void
    {
        $zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'Jump'], 'accent' => 'jump', 'color' => '#FF5B22', 'position' => 1, 'is_active' => true]);
        $type = TicketType::create([
            'name' => ['es' => 'Cumpleaños Jump'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $zone->id,
            'duration_min' => 120, 'min_qty' => 2, 'max_qty' => 20, 'deposit_type' => TicketType::DEPOSIT_NONE,
            'deposit_value' => 0, 'seats_per_unit' => 1, 'tax_rate' => 21, 'is_sellable' => true, 'is_active' => true,
            'guest_fields' => [
                ['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']],
                ['key' => 'allergy', 'type' => 'text', 'required' => false, 'label' => ['es' => 'Alergia']],
            ],
        ]);
        $user = User::factory()->create();
        $order = Order::create(['user_id' => $user->id, 'code' => 'JJ-'.Str::upper(Str::random(6)), 'status' => Order::STATUS_PAID, 'paid_at' => now()]);
        $item = $order->items()->create(['ticket_type_id' => $type->id, 'quantity' => 2, 'unit_price' => 1000, 'seats' => 2]);

        $html = $this->actingAs($user)->get(route('reservation.guests', ['reservation' => $item]))->assertOk()->getContent();

        $this->assertStringNotContainsString('eventfields__req', $html, 'volvió el asterisco de lo obligatorio');
        $this->assertSame(2, substr_count($html, '<span class="gf-opt">'), 'la columna opcional se marca en cada una de las dos fichas');
        $this->assertMatchesRegularExpression('#<a class="gf-legal" href="[^"]*privacidad[^"]*">#', $html, 'la política tiene que ser un control propio (F-08)');
        $this->assertMatchesRegularExpression('#<div class="gf-stub" data-surface="ink">#', $html, 'el resguardo declara su superficie en el marcado');
    }

    /** El bloque de la hoja en `site.css`, sin comentarios. */
    private function sheetCss(): string
    {
        $css = (string) file_get_contents(public_path('css/site.css'));
        $title = strpos($css, 'Formulario post-reserva — HOJA ENFOCADA');
        $this->assertNotFalse($title, 'no se encuentra el bloque de la hoja en site.css');
        // Desde la APERTURA de su comentario de cabecera, o el recorte empezaría a mitad de un
        // comentario y su prosa no se podría quitar.
        $start = strrpos(substr($css, 0, $title), '/* ====');
        $end = strpos($css, '/* ====', $title);
        $this->assertNotFalse($end, 'no se encuentra el final del bloque de la hoja');

        return (string) preg_replace('#/\*.*?\*/#s', '', substr($css, $start, $end - $start));
    }
}
