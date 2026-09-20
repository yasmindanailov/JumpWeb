<?php

namespace Tests\Feature\Reservation;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Muchos invitados (`docs/specs/celebracion-e-invitacion.md` T2, `#571`): fichas agrupadas por estado, «Falta …»
 * en la ficha a medias, la barra de guardar pegada, el pegado de la lista y —lo que más importa— que el orden
 * nuevo de la PÁGINA no reordene a los invitados al guardar.
 *
 * ⚠️ La lógica pura del pegado y del estado de una ficha se prueba con `node --test`
 * (`resources/js/guest-form/logic.test.js`); aquí se prueba lo que pinta el servidor y lo que guarda.
 */
class GuestFormManyGuestsTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_cards_come_first_and_ready_ones_fold_into_a_group(): void
    {
        [$user, $item] = $this->reservation([['name' => 'Ana', 'age' => '7'], [], ['name' => 'Carla', 'age' => '8']]);

        $html = $this->actingAs($user)->get(route('reservation.guests', ['reservation' => $item]))->assertOk()->getContent();

        $pending = strpos($html, 'data-i="1"');
        $group = strpos($html, '<details class="gf-done" id="gf-done">');
        $first = strpos($html, 'data-i="0"');
        $third = strpos($html, 'data-i="2"');

        $this->assertNotFalse($group, 'las fichas listas tienen que ir plegadas en su grupo');
        $this->assertTrue($pending < $group && $group < $first && $first < $third, 'orden de la página: la pendiente arriba, después el grupo con las listas en su orden');
        $this->assertStringContainsString(__('guestform.group_pending', ['count' => 1]), $html);
        $this->assertStringContainsString(trans_choice('guestform.group_done', 2, ['count' => 2]), $html);
    }

    public function test_a_card_with_some_data_says_what_it_is_missing_and_a_blank_one_does_not(): void
    {
        [$user, $item] = $this->reservation([['name' => 'Iker'], []]);

        $html = $this->actingAs($user)->get(route('reservation.guests', ['reservation' => $item]))->assertOk()->getContent();

        $this->assertStringContainsString(__('guestform.status_missing', ['field' => 'Edad']), $html);
        $this->assertSame(1, substr_count($html, 'gf-fiche__role is-missing'), 'solo la ficha a medias dice qué le falta; la de en blanco no');
    }

    public function test_saving_with_the_cards_out_of_order_keeps_every_guest_in_place(): void
    {
        // ⚠️⚠️ La página pinta las pendientes ARRIBA, así que el POST llega con las claves desordenadas. PHP
        // conserva ese orden y `array_values` lo convertía en posiciones: cada guardado movía a los invitados.
        [$user, $item] = $this->reservation([[], [], []]);

        $this->actingAs($user)->post(route('reservation.guests.store', ['reservation' => $item]), [
            'guests' => [
                2 => ['name' => 'Carla', 'age' => '8'],
                0 => ['name' => 'Ana', 'age' => '7'],
                1 => ['name' => 'Bruno', 'age' => '6'],
            ],
        ])->assertRedirect();

        $this->assertSame(['Ana', 'Bruno', 'Carla'], array_column($item->fresh()->guest_data, 'name'));
    }

    /**
     * ⚠️⚠️ **Y con RECORTE de por medio, que es cuando las dos reglas se pisan** (`#718`).
     *
     * Al bajar invitados las fichas se compactan para que lo que se pierda sean las VACÍAS
     * (`§7.1·5`), y esa compactación **tiene que correr sobre las claves YA ordenadas**: hacerlo
     * sobre el orden de llegada deshace el arreglo de arriba y el cliente pierde a otro niño
     * distinto del que creía. Sin recorte no se compacta, así que el caso de arriba no lo ve.
     */
    public function test_saving_out_of_order_while_the_list_shrinks_still_orders_by_key_first(): void
    {
        [$user, $item] = $this->reservation([[], [], []]);
        $item->forceFill(['quantity' => 2, 'seats' => 2])->save();

        // Llegan desordenadas —la página pinta las pendientes arriba— y son TRES para dos plazas.
        $this->actingAs($user)->post(route('reservation.guests.store', ['reservation' => $item]), [
            'guests' => [
                2 => ['name' => 'Carla', 'age' => '8'],
                0 => ['name' => 'Ana', 'age' => '7'],
                1 => [],
            ],
        ])->assertRedirect();

        // Por CLAVE la lista es [Ana, vacía, Carla] → se compacta a [Ana, Carla, vacía] → caben las
        // dos escritas. Compactando sobre el orden de LLEGADA saldría [Carla, Ana].
        $this->assertSame(['Ana', 'Carla'], array_column($item->fresh()->guest_data, 'name'));
    }

    public function test_the_save_bar_and_the_paste_exist_only_while_the_form_can_be_edited(): void
    {
        [$user, $item] = $this->reservation([[], []]);

        $editable = $this->actingAs($user)->get(route('reservation.guests', ['reservation' => $item]))->assertOk()->getContent();
        $this->assertStringContainsString('<div class="gf-savebar">', $editable);
        $this->assertStringContainsString('<dialog class="gf-dialog" id="gf-paste"', $editable);
        $this->assertMatchesRegularExpression('#<script type="module">\s*import \{[^}]+\} from \'[^\']*js/guest-form/logic\.js\?v=\d+\'#', $editable, 'la página importa la lógica pura con su fecha de fichero');

        $item->forceFill(['slot_id' => $this->pastSlot($item)->id])->save();
        $readonly = $this->actingAs($user)->get(route('reservation.guests', ['reservation' => $item]))->assertOk()->getContent();

        // ⚠️ Se buscan los ELEMENTOS y no los nombres de clase sueltos: el script del módulo nombra `gf-savebar-done`,
        // `gf-paste` y `gf-done` para buscarlos, y una subcadena sobre la página entera daba la solo lectura por rota
        // con la página sana (la trampa de `#553`, esta vez con el JS en el papel de la prosa).
        $this->assertStringNotContainsString('<div class="gf-savebar">', $readonly, 'en solo lectura no hay nada que guardar');
        $this->assertStringNotContainsString('<dialog class="gf-dialog"', $readonly, 'en solo lectura no se pega nada');
        $this->assertStringNotContainsString('<details class="gf-done"', $readonly, 'en solo lectura la lista se lee en su orden, sin agrupar');
    }

    public function test_the_page_serves_the_logic_module_it_imports(): void
    {
        // Un módulo que no se sirve deja la página en `no-js`: completa, pero sin pegado ni agrupado vivo.
        $this->assertFileExists(public_path('js/guest-form/logic.js'));
    }

    /**
     * Una reserva de cumpleaños PAGADA con el nombre y la edad obligatorios y la alergia opcional.
     *
     * @param  list<array<string,string>>  $rows
     * @return array{0: User, 1: OrderItem}
     */
    private function reservation(array $rows): array
    {
        $zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'Jump'], 'accent' => 'jump', 'color' => '#FF5B22', 'position' => 1, 'is_active' => true]);
        $type = TicketType::create([
            'name' => ['es' => 'Cumpleaños Jump'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $zone->id,
            'duration_min' => 120, 'min_qty' => 1, 'max_qty' => 20, 'deposit_type' => TicketType::DEPOSIT_NONE,
            'deposit_value' => 0, 'seats_per_unit' => 1, 'tax_rate' => 21, 'is_sellable' => true, 'is_active' => true,
            'guest_fields' => [
                ['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']],
                ['key' => 'age', 'type' => 'number', 'required' => true, 'label' => ['es' => 'Edad']],
                ['key' => 'allergy', 'type' => 'text', 'required' => false, 'label' => ['es' => 'Alergia']],
            ],
        ]);
        $user = User::factory()->create();
        $order = Order::create(['user_id' => $user->id, 'code' => 'JJ-'.Str::upper(Str::random(6)), 'status' => Order::STATUS_PAID, 'paid_at' => now()]);
        $item = $order->items()->create([
            'ticket_type_id' => $type->id, 'quantity' => count($rows), 'unit_price' => 1000, 'seats' => count($rows),
            'guest_data' => $rows,
        ]);

        return [$user, $item];
    }

    private function pastSlot(OrderItem $item): Slot
    {
        return Slot::create([
            'zone_id' => $item->ticketType->zone_id, 'date' => now()->subDays(2)->toDateString(),
            'start_time' => '10:00:00', 'end_time' => '12:00:00', 'capacity' => 20, 'online_capacity' => 20,
        ]);
    }
}
