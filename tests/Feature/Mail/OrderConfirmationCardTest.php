<?php

namespace Tests\Feature\Mail;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\CustomerCard;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\CardToken;
use App\Domain\Identity\Services\CustomerCards;
use App\Domain\Payments\Models\Payment;
use App\Notifications\OrderConfirmation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * Fase 6 · subsistema A — el CARNÉ QR viaja en el correo de confirmación como PNG adjunto
 * (`specs/identidad-qr-puerta.md` §4.10, §9.2 A·8): nace si no existe, es un PNG de verdad, y con la
 * clave de cifrado rotada el correo sale sin adjunto en vez de fallar.
 */
class OrderConfirmationCardTest extends TestCase
{
    use RefreshDatabase;

    private function paidOrder(User $user): Order
    {
        $rateId = (int) RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0])->id;
        $zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true]);
        $entry = TicketType::create([
            'name' => ['es' => 'Entrada 1h'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $zone->id,
            'duration_min' => 60, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
        $entry->prices()->create(['rate_type_id' => $rateId, 'amount_cents' => 1000]);
        $slot = Slot::create(['zone_id' => $zone->id, 'date' => now()->addDays(3)->toDateString(), 'start_time' => '10:00:00', 'end_time' => '11:00:00', 'capacity' => 20, 'online_capacity' => 20]);
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'R-MAIL01', 'status' => Order::STATUS_PAID,
            'subtotal' => 2000, 'tax' => 0, 'total' => 2000, 'currency' => 'EUR', 'paid_at' => now(),
        ]);
        $order->items()->create(['ticket_type_id' => $entry->id, 'slot_id' => $slot->id, 'quantity' => 2, 'unit_price' => 1000, 'seats' => 2]);
        Payment::create(['payable_type' => (new Order)->getMorphClass(), 'payable_id' => $order->id, 'provider' => 'redsys', 'amount' => 2000, 'currency' => 'EUR', 'status' => Payment::STATUS_PAID, 'paid_at' => now()]);

        return $order->fresh();
    }

    public function test_the_confirmation_attaches_the_card_as_a_png_and_issues_it_if_needed(): void
    {
        $user = User::factory()->create(['locale' => 'es']);
        $order = $this->paidOrder($user);
        $this->assertSame(0, CustomerCard::count());

        $mail = (new OrderConfirmation($order))->toMail($user);

        $card = app(CustomerCards::class)->activeFor($user);
        $this->assertNotNull($card, 'el carné nace al componer el correo');
        $this->assertCount(1, $mail->rawAttachments);
        $attachment = $mail->rawAttachments[0];
        $this->assertSame('carne-qr.png', $attachment['name']);
        $this->assertSame('image/png', $attachment['options']['mime']);
        $this->assertStringStartsWith("\x89PNG", $attachment['data'], 'un PNG de verdad, no un SVG');
        $this->assertGreaterThan(500, strlen($attachment['data']));
        $this->assertContains(__('emails.order_confirmation.card_attached'), $mail->introLines, 'el correo dice que va adjunto');

        // Un reenvío no emite otro carné.
        (new OrderConfirmation($order))->toMail($user);
        $this->assertSame(1, CustomerCard::count());
        $this->assertTrue(CardToken::isWellFormed((string) $card->plainToken()));
    }

    public function test_the_line_exists_in_the_three_languages_and_differs(): void
    {
        $texts = [];
        foreach (['es', 'en', 'fr'] as $locale) {
            $this->assertTrue(Lang::has('emails.order_confirmation.card_attached', $locale, false), "falta en {$locale}");
            $texts[$locale] = Lang::get('emails.order_confirmation.card_attached', [], $locale);
        }
        $this->assertCount(3, array_unique($texts), 'existir no es estar traducido');
    }

    /** §8.1: la clave rotada degrada —el correo sale SIN el adjunto— en vez de romper el envío. */
    public function test_with_a_rotated_app_key_the_mail_goes_out_without_the_attachment(): void
    {
        $user = User::factory()->create(['locale' => 'es']);
        $order = $this->paidOrder($user);
        app(CustomerCards::class)->ensureFor($user);

        Model::encryptUsing(new Encrypter(Encrypter::generateKey('AES-256-CBC'), 'AES-256-CBC'));
        try {
            $mail = (new OrderConfirmation($order))->toMail($user);
        } finally {
            Model::encryptUsing(null);
        }

        $this->assertSame([], $mail->rawAttachments);
        $this->assertNotContains(__('emails.order_confirmation.card_attached'), $mail->introLines);
        $this->assertSame(1, CustomerCard::count(), 'y no se emite otro por debajo: el titular rota el suyo');
    }
}
