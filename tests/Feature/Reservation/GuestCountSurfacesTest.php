<?php

namespace Tests\Feature\Reservation;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\PostFormAddons;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Services\DisplayTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * **LAS DOS SUPERFICIES por las que el cliente cambia sus invitados** (`specs/invitados-en-post-form.md`
 * §4.7 y §4.8, `DECISIONES #444`): la página firmada y la API.
 *
 * ❗❗❗ **Lo que estos casos vigilan no es «que funcione», es el ORDEN** — capturar el testigo,
 * ajustar, RE-LEER, sanear las fichas, extras—, que es donde la revisión adversarial encontró dos
 * defectos antes de escribirlos (§7.1·A2/A3):
 *
 *  · ajustar antes de capturar el testigo deja a los EXTRAS sin poder comprarse nunca;
 *  · ajustar sin re-leer recorta las fichas contra la cantidad VIEJA — el hueco original, reproducido
 *    dentro de su propio arreglo.
 *
 * ⚠️ Y el rechazo **no puede tumbar el guardado de los nombres y las alergias**: son transacciones
 * distintas a propósito, y el desenlace lo DICE.
 */
class GuestCountSurfacesTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $pack;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->date = Carbon::today()->addDays(10)->toDateString();

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0, 'is_active' => true]);

        $this->zone = Zone::create([
            'slug' => 'cumpleanos', 'name' => ['es' => 'Cumpleaños'], 'is_active' => true,
            'max_per_slot' => 3, 'max_guests_per_slot' => 40, 'prep_blocks_cupo' => false, 'position' => 1,
        ]);

        foreach (range(15, 19) as $hour) {
            Slot::create([
                'zone_id' => $this->zone->id, 'date' => $this->date,
                'start_time' => sprintf('%02d:00:00', $hour),
                'end_time' => sprintf('%02d:00:00', $hour + 1),
                'capacity' => 100, 'online_capacity' => 100,
            ]);
        }

        $this->pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $this->zone->id,
            'duration_min' => 120, 'min_qty' => 8, 'max_qty' => 20, 'seats_per_unit' => 1,
            'deposit_type' => TicketType::DEPOSIT_NONE, 'deposit_value' => 0,
            'is_sellable' => true, 'is_active' => true, 'position' => 1,
            'guest_fields' => [
                ['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']],
                ['key' => 'allergy', 'type' => 'text', 'required' => false, 'label' => ['es' => 'Alergia']],
            ],
        ]);
    }

    // ─── La página firmada ───────────────────────────────────────────────────────────

    public function test_the_signed_page_offers_the_control_with_its_bounds(): void
    {
        $item = $this->reservation(10);

        $html = $this->get($item->guestFormSignedUrl())
            ->assertOk()
            ->assertSee('name="guest_count"', false)
            ->assertSee('id="fiesta-form"', false)
            // El SUELO es el mínimo del pack y el TECHO su máximo, resueltos por el dominio.
            ->assertSee('min="8"', false)
            ->assertSee('max="20"', false)
            ->getContent();

        // ⚠️⚠️ En la piel vieja el campo vivía en el RESGUARDO, FUERA del formulario, y sin `form=` el navegador no
        // lo enviaba: cambiar el número no hacía nada en un navegador real (`#571`). Los casos de abajo mandan el
        // POST a mano, así que ninguno podía verlo. En la piel nueva (`fiesta-sistema-nuevo.md` §4.2) la zona 3 va
        // DENTRO del único formulario: lo que se afirma es que el campo sigue entre `<form>` y `</form>`.
        $form = strpos($html, 'id="fiesta-form"');
        $campo = strpos($html, 'name="guest_count"');
        $this->assertNotFalse($form);
        $this->assertNotFalse($campo);
        $this->assertGreaterThan($form, $campo, 'el campo del número está antes del formulario');
        $this->assertLessThan(strpos($html, '</form>', $form), $campo, 'el campo del número está FUERA del formulario: el navegador no lo enviaría (`#571`)');
    }

    public function test_the_signed_page_saves_the_new_count_together_with_the_cards(): void
    {
        // ❗ El caso que fija el ORDEN: se mandan 12 fichas Y `guest_count = 12` sobre una línea de 10.
        // Con el ajuste después del saneo —o sin re-leer la reserva— se guardarían DIEZ, que es el
        // hueco original metido dentro de su propio arreglo.
        $item = $this->reservation(10);
        $guests = [];
        for ($i = 0; $i < 12; $i++) {
            $guests[$i] = ['name' => 'Nino '.($i + 1)];
        }

        $this->post($item->guestFormSignedStoreUrl(), [
            'guest_count' => 12,
            'guests' => $guests,
        ])->assertRedirect();

        $fresh = $item->fresh();
        $this->assertSame(12, (int) $fresh->quantity);
        $this->assertCount(12, $fresh->guestData());
        $this->assertSame('Nino 12', $fresh->guestData()[11]['name'] ?? null);
    }

    public function test_a_rejected_count_still_saves_the_cards_and_says_why(): void
    {
        // ⚠️ Las dos escrituras son transacciones distintas a propósito: que el número no quepa no
        // puede tumbar el guardado de los nombres y las alergias, que es la razón de ser de la página.
        $item = $this->reservation(10);

        $this->post($item->guestFormSignedStoreUrl(), [
            'guest_count' => 25,   // por encima del máximo del pack
            'guests' => [['name' => 'Ana']],
        ])->assertRedirect()->assertSessionHas('status', 'guest-count-above_max');

        $fresh = $item->fresh();
        $this->assertSame(10, (int) $fresh->quantity, 'la cantidad no se movió');
        $this->assertSame('Ana', $fresh->guestData()[0]['name'] ?? null, 'las fichas SÍ se guardaron');
    }

    public function test_a_body_without_guest_count_leaves_the_count_alone(): void
    {
        // La semántica de la ausencia, la misma que `guests` y `general` (`#413` T0): ausente = no la
        // toques. Un `?? $quantity` la convertiría en un cambio que nadie pidió.
        $item = $this->reservation(10);

        $this->post($item->guestFormSignedStoreUrl(), ['guests' => [['name' => 'Ana']]])
            ->assertRedirect();

        $this->assertSame(10, (int) $item->fresh()->quantity);
    }

    public function test_the_control_is_not_offered_when_the_deadline_has_passed(): void
    {
        // ⚠️ El control **no desaparece: se deshabilita con su motivo**. Un control que se esconde sin
        // explicación es cómo el hueco original estuvo meses sin que nadie lo viera.
        $item = $this->reservation(10);
        $this->travelTo(Carbon::parse($this->date.' 16:00', DisplayTime::timezone())->subDay());

        $this->get($item->guestFormSignedUrl())
            ->assertOk()
            ->assertDontSee('name="guest_count"', false)
            ->assertSee(__('guestform.count_closed_cutoff'));
    }

    public function test_the_discard_warning_travels_in_a_form_the_browser_can_render(): void
    {
        // ⚠️⚠️ **Guarda nacida de un defecto propio, y CAMBIÓ DE PREMISA en `#571`** (el precedente del
        // `SlotOfferTest` de `#324`). El aviso lo pinta el JS, y en el navegador no hay `trans_choice`: con la
        // sintaxis de plural de Laravel, la barra vertical y las dos formas llegaban enteras a la pantalla, así
        // que la cadena tenía UNA sola forma —y la pantalla decía «de 1 fichas»—.
        // ▶ Desde la T2 el JS SÍ pluraliza: `choice()`, hoy en `resources/js/fiesta/logica.js`, con sus casos de
        // `node --test`. Lo que se vigila ahora es la PAREJA: si la cadena trae dos formas, el script tiene que
        // resolverlas con `choice()` y no con un `replace` a secas, que es exactamente cómo volvería el defecto.
        // ⚠️ En la piel nueva la plantilla viaja en `data-tpl` del aviso y el script va EMPAQUETADO por Vite: el
        // emparejamiento se afirma sobre el fuente del script, no sobre el HTML.
        $item = $this->reservation(10);

        $this->get($item->guestFormSignedUrl())
            ->assertOk()
            ->assertSee('id="pli-aviso-numero"', false)
            ->assertSee(':discarded', false);

        $this->assertStringContainsString(
            'choice(aviso.dataset.tpl', (string) file_get_contents(base_path('resources/js/fiesta/lista.js')),
            'el aviso de fichas perdidas no pasa por `choice()`: una cadena con dos formas llegaría entera a la pantalla',
        );

        foreach (['es', 'en', 'fr'] as $locale) {
            $text = (string) __('guestform.count_warn_discard', [], $locale);
            $forms = explode('|', $text);

            $this->assertLessThanOrEqual(2, count($forms), "«count_warn_discard» ({$locale}) trae más formas de las que resuelve `choice()` sin intervalos.");
            foreach ($forms as $form) {
                $this->assertStringContainsString(':count', $form, "«count_warn_discard» ({$locale}): a una forma le falta :count");
                $this->assertStringContainsString(':discarded', $form, "«count_warn_discard» ({$locale}): a una forma le falta :discarded");
            }
        }
    }

    // ─── La API ──────────────────────────────────────────────────────────────────────

    public function test_the_api_publishes_the_bounds_already_resolved(): void
    {
        $item = $this->reservation(10);

        $this->getJson($this->apiUrl($item))
            ->assertOk()
            ->assertJsonPath('guest_count_editable', true)
            ->assertJsonPath('guest_count_min', 8)
            ->assertJsonPath('guest_count_max', 20)
            ->assertJsonPath('guest_count_locked_reason', null);
    }

    public function test_the_api_changes_the_count_and_keeps_the_two_writes_apart(): void
    {
        $item = $this->reservation(10);
        $save = $this->getJson($this->apiUrl($item))->json('save_url');

        $this->putJson($save, ['guest_count' => 14, 'guests' => [['name' => 'Ana']]])
            ->assertOk()
            ->assertJsonPath('guest_count', 14);

        $this->assertSame(14, (int) $item->fresh()->quantity);
    }

    public function test_the_api_rejects_the_count_without_losing_the_cards(): void
    {
        $item = $this->reservation(10);
        $save = $this->getJson($this->apiUrl($item))->json('save_url');

        $this->putJson($save, ['guest_count' => 3, 'guests' => [['name' => 'Ana']]])
            ->assertOk()
            ->assertJsonPath('guest_count', 10);

        $this->assertSame('Ana', $item->fresh()->guestData()[0]['name'] ?? null);
    }

    public function test_the_api_leaves_the_count_alone_when_the_key_is_absent(): void
    {
        $item = $this->reservation(10);
        $save = $this->getJson($this->apiUrl($item))->json('save_url');

        $this->putJson($save, ['guests' => [['name' => 'Ana']]])->assertOk();

        $this->assertSame(10, (int) $item->fresh()->quantity);
    }

    public function test_changing_the_count_does_not_break_buying_an_extra_in_the_same_save(): void
    {
        // ❗❗ **El caso de §7.1·A2, y sin él este arreglo rompería los extras en silencio**: el
        // testigo de los extras se sustituye por el de después SOLO si coincide con el de antes de
        // NUESTRA propia escritura. Con el ajuste antes de esa captura, un guardado normal diría
        // «la reserva ha cambiado mientras tenías esta página abierta» y no compraría nada.
        $item = $this->reservation(10);
        $version = PostFormAddons::versionOf($item);

        $this->post($item->guestFormSignedStoreUrl(), [
            'expected_version' => $version,
            'guest_count' => 12,
            'guests' => [['name' => 'Ana']],
        ])->assertRedirect()->assertSessionHas('status', 'guest-form-saved');

        $this->assertSame(12, (int) $item->fresh()->quantity);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────────────

    private function apiUrl(OrderItem $item): string
    {
        return $item->guestFormApiUrls()['show'] ?? '';
    }

    private function reservation(int $guests): OrderItem
    {
        $user = User::factory()->create();
        $slot = Slot::where('zone_id', $this->zone->id)->where('start_time', '15:00:00')->firstOrFail();

        $order = Order::create([
            'user_id' => $user->id, 'code' => 'JJ-'.Str::upper(Str::random(6)),
            'status' => Order::STATUS_PAID, 'paid_at' => now(),
            'total' => $guests * 1495, 'currency' => 'EUR',
        ]);
        Payment::create([
            'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id,
            'amount' => $guests * 1495, 'currency' => 'EUR', 'provider' => 'redsys',
            'status' => Payment::STATUS_PAID, 'paid_at' => now(),
            'gateway_order' => str_pad((string) (500000 + $order->id), 10, '0', STR_PAD_LEFT),
        ]);
        $order->items()->create([
            'ticket_type_id' => $this->pack->id, 'slot_id' => $slot->id, 'quantity' => $guests,
            'unit_price' => 1495, 'seats' => $guests, 'event_data' => [],
        ]);

        return $order->items()->whereNull('parent_item_id')->with(['ticketType', 'slot', 'order'])->firstOrFail();
    }
}
